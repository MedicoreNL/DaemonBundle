<?php

namespace DaemonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Uecode\Daemon\Exception;

/**
 * Base daemon command from which to extend.
 */
abstract class DaemonCommand extends ContainerAwareCommand
{
    const EVENT_START = 'EVENT_START';

    const EVENT_CYCLE_START = 'EVENT_CYCLE_START';

    const EVENT_CYCLE_END = 'EVENT_CYCLE_END';

    const EVENT_STOP = 'EVENT_STOP';

    /**
     * @var \DaemonBundle\Service\DaemonService
     */
    protected $daemon;

    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $help;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    protected $events = array();

    /**
     * @var array
     */
    protected $methods = array('start', 'stop', 'restart');

    /**
     * @var boolean
     */
    protected $verbose;

    /**
     * Returns the input interface.
     *
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Sets the input interface.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \DaemonBundle\Command\DaemonCommand
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Configures the command.
     */
    final protected function configure()
    {
        $this->addMethods();

        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->setHelp($this->help)
            ->addArgument('method', InputArgument::REQUIRED, implode('|', $this->methods));

        $this->setArguments();
        $this->setOptions();
    }

    /**
     * Add new methods to the daemon.
     */
    protected function addMethods()
    {
    }

    /**
     * Set the arguments for the command.
     */
    protected function setArguments()
    {
    }

    /**
     * Set the options for the command.
     */
    protected function setOptions()
    {
    }

    /**
     * Adds the given method to the list of allowed methods.
     *
     * @param string $method
     */
    protected function addMethod($method)
    {
        if (false === array_key_exists($method, $this->methods)) {
            $this->methods[] = $method;
        }
    }

    /**
     * Validate any input arguments/options before starting the daemon.
     *
     * @return boolean
     */
    protected function validate()
    {
        return true;
    }

    /**
     * Runs the specified method supplied on command line.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     *
     * @throws \Exception
     */
    final protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (false === $this->validate($input, $output)) {
            return 1;
        }

        $method = $input->getArgument('method');
        if (!in_array($method, $this->methods)) {
            throw new \Exception(sprintf('Method must be one of: %s', implode(', ', $this->methods)));
        }
        $this->setInput($input);
        $this->setOutput($output);

        if ($input->getOption('verbose')) {
            $this->verbose = true;
        }

        $this->container = $this->getContainer();

        $this->createDaemon();
        call_user_func(array($this, $method));

        return 0;
    }

    /**
     * Creates and initializes the daemon.
     */
    final protected function createDaemon()
    {
        $this->daemon = $this->container->get('daemon.daemon_service');
        $daemonName = strtolower(str_replace(':', '_', $this->getName()));
        if (!$this->container->hasParameter($daemonName . '.daemon.options')) {
            throw new \Exception(sprintf("Could not find a daemon for %s", $daemonName . '.daemon.options'));
        }

        $this->daemon->initialize($this->container->getParameter($daemonName . '.daemon.options'));
    }

    /**
     * Adds an event to the command.
     *
     * @param string            $type
     * @param string            $name
     * @param \Closure|callable $function
     *
     * @throws \Exception
     */
    protected function addEvent($type, $name, $function)
    {
        if (is_callable($function) || $function instanceof \Closure) {
            if (is_string($name)) {
                $this->events[$type][$name] = $function;
            } else {
                throw new \Exception("Name passed is not a string.");
            }
        } else {
            throw new \Exception("Function passed is not a callable or a closure.");
        }
    }

    /**
     * Removes the named event for a given type.
     *
     * @param string $type
     * @param string $name
     */
    protected function removeEvent($type, $name)
    {
        unset($this->events[$type][$name]);
    }

    /**
     * Starts the daemon.
     *
     * @throws \DaemonBundle\Exception
     */
    final protected function start()
    {
        if ($this->daemon->isRunning()) {
            throw new Exception('Daemon is already running!');
        }

        $this->daemon->start();

        $this->runEvents(self::EVENT_START);

        while ($this->daemon->isRunning()) {
            $this->runEvents(self::EVENT_CYCLE_START);
            $this->daemonLogic();
            $this->runEvents(self::EVENT_CYCLE_END);
        }

        $this->runEvents(self::EVENT_STOP);
        $this->daemon->stop();
    }

    /**
     * Restarts the daemon.
     *
     * @throws \DaemonBundle\Exception
     */
    final protected function restart()
    {
        if (!$this->daemon->isRunning()) {
            throw new Exception('Daemon is not running!');
        }

        $this->daemon->restart();
        $this->runEvents(self::EVENT_START);
        while ($this->daemon->isRunning()) {
            $this->runEvents(self::EVENT_CYCLE_START);
            $this->daemonLogic();
            $this->runEvents(self::EVENT_CYCLE_END);
        }

        $this->runEvents(self::EVENT_STOP);
        $this->daemon->stop();
    }

    /**
     * Stops the daemon.
     *
     * @throws \DaemonBundle\Exception
     */
    final protected function stop()
    {
        if (!$this->daemon->isRunning()) {
            throw new Exception('Daemon is not running!');
        }
        $this->runEvents(self::EVENT_STOP);
        $this->daemon->stop();
    }

    /**
     * Runs all events for the specified type.
     *
     * @param string $type
     */
    protected function runEvents($type)
    {
        $this->log("Finding all {$type} events and running them. ");
        $events = $this->getEvents($type);
        foreach ($events as $name => $event) {
            $this->log("Running the `{$name}` {$type} event. ");
            if ($event instanceof \Closure) {
                $event($this);
            } else {
                call_user_func_array($event, array($this));
            }
        }
    }

    /**
     * Logs  a message.
     *
     * @param string $content
     * @param string $level
     */
    protected function log($content = '', $level = 'info')
    {
        if (true === $this->verbose) {
            $this->getOutput()->writeln(sprintf("<%s>%s</%s>", $level, $content, $level));
        }

        $this->container->get('logger')->$level($content);
    }

    /**
     * Returns the output interface.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Sets the output interface.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \DaemonBundle\Command\DaemonCommand
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Gets an array of events for the given type.
     *
     * @param string $type
     *
     * @return array
     */
    protected function getEvents($type)
    {
        return array_key_exists($type, $this->events) ? $this->events[$type] : array();
    }

    /**
     * Sets the events for the command.
     */
    protected function setEvents()
    {
        $this->events = array();
    }

    /**
     * Daemon logic.
     */
    abstract protected function daemonLogic();

    /**
     * Gets a service by id.
     *
     * @param string $id
     *
     * @return object
     */
    protected function get($id)
    {
        return $this->container->get($id);
    }
}

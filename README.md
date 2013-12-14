DaemonBundle
============

A Symfony2 bundle that integrates the [uecode/daemon](https://github.com/uecode/daemon) library.

Inspired by [uecode/daemon-bundle](https://github.com/uecode/daemon-bundle). 

Installation
------------

 1. Install [Composer](https://getcomposer.org).

    ```bash
    # Install Composer
    curl -sS https://getcomposer.org/installer | php
    ```

 2. Add this bundle to the `composer.json` file of your project.

    ```bash
    php composer.phar require medicorenl/daemon-bundle dev-master
    ```
    
 3. After installing, you need to require Composer's autloader in the bootstrap of your project.

    ```php
    // app/autoload.php
    $loader = require __DIR__ . '/../vendor/autoload.php';
    ```

 4. Add the bundle to your application kernel.

    ```php
    // app/AppKernel.php
    public function registerBundles()
    {
        return array(
            // ...
            new DaemonBundle\DaemonBundle(),
            // ...
        );
    }
    ```

 5. Configure the bundle by adding parameters to the  `config.yml` file:

    ```yaml
    # app/config/config.yml
    daemon:
        daemons:
            <command name>:
                appName: <name>
                appDir: %kernel.root_dir%
                appDescription: <description>
                logDir: %kernel.logs_dir%
                appPidDir: %kernel.cache_dir%/daemons/
                sysMaxExecutionTime: 0
                sysMaxInputTime: 0
                sysMemoryLimit: 1024M
                appRunAsGID: 1
                appRunAsUID: 1
    ```

Usage
-----

 1. Create a command that extends from ExtendCommand:
 
    ```php
    <?php
    
    namespace <Application>\<Bundle>\Command;
    
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use DaemonBundle\Command\ExtendCommand;
    
    class <Name>Command extends ExtendCommand
    {
        /**
         * @var string
         */
        protected $name = '<name>';
    
        /**
         * @var string
         */
        protected $description = '<description>';
    
        /**
         * @var string
         */
        protected $help = 'Usage: <info>php app/console <name> start|stop|restart</info>';
        
        /**
         * Configures command arguments.
         */
        protected function setArguments()
        {
        }
    
        /**
         * Configures command options.
         */
        protected function setOptions()
        {
        }
        
        /**
         * Starts asynchronous release:deploy commands for queue items.
         */
        protected function daemonLogic()
        {
            $this->container->get('logger')->info('Daemon is running!');
            $this->daemon->iterate(5);
        }
    }
    ```

 2. Optionally create a service from your command: 

    ```xml
    <service id="<bundle alias>.command.<command alias>" class="<Application>\<Bundle>Bundle\Command\<Name>Command" />
    ```

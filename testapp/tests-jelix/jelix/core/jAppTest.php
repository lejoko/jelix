<?php


class jAppTest extends PHPUnit_Framework_TestCase {


    
    function testModulesDirPath() {
        $modules = jApp::getAllModulesPath();

         // first save
        jApp::saveContext();
    
        // verify that we still have the current path
        $this->assertEquals($modules, jApp::getAllModulesPath());
 
        jApp::clearModulesPluginsPath();

        // verify that we have only jelix as modules
        $this->assertEquals(array('jelix'=> JELIX_LIB_PATH.'core-modules/jelix/'), jApp::getAllModulesPath());

        jApp::declareModulesDir(array(
            __DIR__.'/../installer/app1/modules/'
        ));

        $this->assertEquals(
            array(
                  'jelix'=> JELIX_LIB_PATH.'core-modules/jelix/',
                  'aaa'=>realpath(__DIR__.'/../installer/app1/modules/aaa/').'/'
            ),
            jApp::getAllModulesPath());

        // pop the first save, we should be with initial paths
        jApp::restoreContext();
        $this->assertEquals($modules, jApp::getAllModulesPath());
    }

    function testModulePath() {
        jApp::saveContext();
        jApp::clearModulesPluginsPath();

        // verify that we have only jelix as modules
        $this->assertEquals(array('jelix'=> JELIX_LIB_PATH.'core-modules/jelix/'), jApp::getAllModulesPath());

        jApp::declareModule(array(
            __DIR__.'/../installer/app1/modules/aaa/',
            jApp::appPath('modules/testapp')
        ));

        $this->assertEquals(
            array(
                  'jelix'=> JELIX_LIB_PATH.'core-modules/jelix/',
                  'aaa'=>realpath(__DIR__.'/../installer/app1/modules/aaa/').'/',
                  'testapp'=>realpath(jApp::appPath('modules/testapp')).'/'
            ),
            jApp::getAllModulesPath());

        // pop the first save, we should be with initial paths
        jApp::restoreContext();
    }

    function testPluginPath() {
        $plugins = jApp::getAllPluginsPath();
        
        $this->assertEquals(array(
            JELIX_LIB_PATH.'plugins/',
            jApp::appPath('plugins/'),
            LIB_PATH.'jelix-plugins/',
            jApp::appPath('vendor/jelix/php-redis-plugin/plugins/'),
            jApp::appPath('vendor/jelix/minify-module/jminify/plugins/'),
            LIB_PATH.'jelix-modules/jacl2db/plugins/',
            LIB_PATH.'jelix-modules/jacldb/plugins/',
            LIB_PATH.'jelix-modules/jacl/plugins/',
            LIB_PATH.'jelix-modules/jacl2/plugins/',
            ), $plugins);

        jApp::saveContext();

        $this->assertEquals($plugins, jApp::getAllPluginsPath());
        

        jApp::clearModulesPluginsPath();
        $this->assertEquals(array(JELIX_LIB_PATH.'plugins/'), jApp::getAllPluginsPath());

        
        jApp::restoreContext();
        $this->assertEquals($plugins, jApp::getAllPluginsPath());
    }
}
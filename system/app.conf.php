<?php
/**
 * Selenium MCP Server — Application Configuration
 * Enchilada Framework 3.0
 */

define('APPLICATION_NAME', 'Selenium MCP');
define('APPLICATION_SLUG', 'selenium-mcp');
define('APPLICATION_VERSION', '0.3.1');
define('APPLICATION_WEBSITE', 'https://pacyworld.dev/pacyworld/selenium-mcp');

define('APPLICATION_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APPLICATION_CONFDIR', (getenv('ENCHILADA_CONF_DIR') ?: (@$MULTISITE_CONFDIR ?: APPLICATION_ROOT . 'config' . DIRECTORY_SEPARATOR)));
define('APPLICATION_DEBUG', getenv('ENCHILADA_DEBUG_ENABLE'));
define('APPLICATION_USERAGENT', sprintf('%s/%s (%s; U; %s %s) PHP %s', APPLICATION_NAME, APPLICATION_VERSION, php_uname('s'), php_uname('s'), php_uname('r'), phpversion()));
define('APPLICATION_TEMPDIR', (getenv('ENCHILADA_TEMP_DIR') ?: APPLICATION_ROOT . 'temp' . DIRECTORY_SEPARATOR));
define('APPLICATION_TIMEZONE', 'UTC');

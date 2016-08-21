<?php

class sn_module {
  /**
   * @var sn_module[]
   */
  protected static $sn_module = array();

  public static $sn_module_list = array();

  public $manifest = array(
    'package'   => 'core',
    'name'      => 'sn_module',
    'version'   => '1c0',
    'copyright' => 'Project "SuperNova.WS" #41a52.34# copyright © 2009-2014 Gorlum',

    'require'       => array(),
    'root_relative' => '',

    'installed' => true,
    'active'    => true,

    // 'constants' array - contents of this array would be instaled into engine. 'UNIT_STRUCTURE_NEW' => 999999,
    'constants' => array(),

    'vars'      => array(), // Just a placeholder. vars assigned via special method __assign_vars(). Need 'cause of new constants that can be defined within module. See below

    /**
     * 'functions' array - this functions would be installed as hooks
     * Key: overwritable function name to replace
     * Value: which method to use. Format: [*][<object_name>][.]<method>
     * '*' means that new function would replace old one
     * If object_name is ommited but "." is present - hook linked to global function
     * If only "method" present - overwritable linked to appropriate method of current object
     * Examples:
     * 'test_object.test_method' - will
     * Function/Method should be accessible on module init
     * //      'test_object_test_method' => 'test_object.test_method',
     * //      'test_function' => '.my_test_function',
     * //      'this_object_test_method' => 'test_method',
     */
    'functions' => array(),

    // 'menu' array - this menu items would be merged into main game menu
    // Array element almost identical to $sn_menu with additional param 'LOCATION'.
    // 'LOCATION' => '-news', // Special atrtribute for modules
    // [-|+][<menu_item_id>]
    // <menu_item_id> identifies menu item aginst new menu item would be placed. When ommited new item placed against whole menu
    // -/+ indicates that new item should be placed before/after identified menu item (or whole menu). If ommited and menu item exists - new item will replace previous one
    // Empty or non-existent LOCATION equivalent to '+' - place item at end of menu
    // Non-existent menu_item_id treated as ommited
    'menu'      => array(),

    // 'page' array - defines pages which will handle this module and appropriate handlers
    'page'      => array(),
  );

  protected $config = array();

  protected $module_full_class_path = __FILE__;

  /**
   * Динамическое назначение переменных
   *
   * Актуально, когда записываемые данные зависят от статуса игры
   * Например - назначаются константы внутри модуля
   *
   * @return array
   */
  public function __assign_vars() {
    return array();
  }

  public function loadModuleRootConfig() {
    require SN_ROOT_PHYSICAL . 'config.php';

    $module_config_array = get_class($this) . '_config';
    if(!empty($$module_config_array) && is_array($$module_config_array)) {
      $this->config = $$module_config_array;

      return true;
    }

    return false;
  }

  public function __construct($filename = __FILE__) {
    // Getting module PHP class name
    $class_module_name = get_class($this);

    // Getting module root relative to SN
    $this->manifest['root_relative'] = str_replace(array(SN_ROOT_PHYSICAL, basename($filename)), '', str_replace('\\', '/', $filename));

    // TODO: Load configuration from DB. Manifest setting
    // Trying to load configuration from file
    if(!$config_exists = $this->loadModuleRootConfig()) {
      // Конфигурация может лежать в config_path в манифеста или в корне модуля
      if(isset($this->manifest['config_path']) && file_exists($config_filename = $this->manifest['config_path'] . '/config.php')) {
        $config_exists = true;
      } elseif(file_exists($config_filename = dirname($filename) . '/config.php')) {
        $config_exists = true;
      }

      if($config_exists) {
        include($config_filename);
        $module_config_array = $class_module_name . '_config';
        $this->config = $$module_config_array;
      }
    }

    // Registering module
    self::$sn_module[$class_module_name] = $this;
  }


  public function initialize() {
    global $sn_menu_extra, $sn_menu_admin_extra;

    // Checking module status - is it installed and active
    $this->check_status();
    if(!$this->manifest['active']) {
      return;
    }

    $this->setSystemConstants();
    $this->setSystemVariables();
    $this->addSystemHandlers();
    $this->mergeI18N();

    // Patching game menu - if any
    isset($this->manifest['menu']) ? $this->mergeMenu($sn_menu_extra, $this->manifest['menu']) : false;
    isset($this->manifest['menu_admin']) ? $this->mergeMenu($sn_menu_admin_extra, $this->manifest['menu_admin']) : false;

    $this->mergeJavascript();
    $this->mergeCss();
    $this->mergeNavbarButton();
  }

  protected function setSystemConstants() {
    // Setting constants - if any
    if(empty($this->manifest['constants']) || !is_array($this->manifest['constants'])) {
      return;
    }

    foreach($this->manifest['constants'] as $constant_name => $constant_value) {
      !defined($constant_name) ? define($constant_name, $constant_value) : false;
    }
  }

  protected function setSystemVariables() {
    // Adding vars - if any
    // Due to possible introduce of new constants in previous step vars is assigned via special method to honor new constants
    // Assignation can work with simple variables and with multidimensional arrays - for ex. 'sn_data[groups][test]'
    // New values from module variables will overwrite previous values (for root variables) and array elements with corresponding indexes (for arrays)
    // Constants as array indexes are honored - it's make valid such declarations as 'sn_data[ques][QUE_STRUCTURES]'
    $this->manifest['vars'] = $this->__assign_vars();
    if(empty($this->manifest['vars']) || !is_array($this->manifest['vars'])) {
      return;
    }

    $vars_assigned = array();
    foreach($this->manifest['vars'] as $var_name => $var_value) {
      $sub_vars = explode('[', str_replace(']', '', $var_name));
      $var_name = $sub_vars[0];

      if(!isset($vars_assigned[$var_name])) {
        $vars_assigned[$var_name] = true;
        global $$var_name;
      }

      $pointer = &$$var_name;
      if(($n = count($sub_vars)) > 1) {
        for($i = 1; $i < $n; $i++) {
          if(defined($sub_vars[$i])) {
            $sub_vars[$i] = constant($sub_vars[$i]);
          }

          if(!isset($pointer[$sub_vars[$i]]) && $i != $n) {
            $pointer[$sub_vars[$i]] = array();
          }
          $pointer = &$pointer[$sub_vars[$i]];
        }
      }

      if(!isset($pointer) || !is_array($pointer)) {
        $pointer = $var_value;
      } elseif(is_array($$var_name)) {
        $pointer = array_merge_recursive_numeric($pointer, $var_value);
      }
    }
  }

  protected function mergeMenu(&$sn_menu_extra, &$menu_patch) {
    if(!is_array($menu_patch)) {
      return;
    }

    foreach($menu_patch as $menu_item_name => $menu_item_data) {
      $sn_menu_extra[$menu_item_name] = $menu_item_data;
    }
  }

  protected function addSystemHandlers() {
    // Overriding function if any
    sn_sys_handler_add(classSupernova::$functions, $this->manifest['functions'], $this);

    foreach(classSupernova::$sn_mvc as $handler_type => &$handler_data) {
      sn_sys_handler_add($handler_data, $this->manifest['mvc'][$handler_type], $this, $handler_type);
    }
  }

  protected function mergeNavbarButton() {
    if(empty($this->manifest['navbar_prefix_button']) || !is_array($this->manifest['navbar_prefix_button'])) {
      return;
    }

    foreach($this->manifest['navbar_prefix_button'] as $button_image => $button_url_relative) {
      classSupernova::$sn_mvc['navbar_prefix_button'][$button_image] = $button_url_relative;
    }
  }

  protected function mergeI18N() {
    $arrayName = 'i18n';
    if(empty($this->manifest[$arrayName]) || !is_array($this->manifest[$arrayName])) {
      return;
    }

    foreach($this->manifest[$arrayName] as $pageName => &$contentList) {
      foreach($contentList as &$i18n_file_data) {
        if(is_array($i18n_file_data) && !$i18n_file_data['path']) {
          $i18n_file_data['path'] = $this->manifest['root_relative'];
        }
      }
      if(!isset(classSupernova::$sn_mvc[$arrayName][$pageName])) {
        classSupernova::$sn_mvc[$arrayName][$pageName] = array();
      }
      classSupernova::$sn_mvc[$arrayName][$pageName] += $contentList;
    }
  }

  protected function mergeArraySpecial($arrayName) {
    if(empty($this->manifest[$arrayName]) || !is_array($this->manifest[$arrayName])) {
      return;
    }

    foreach($this->manifest[$arrayName] as $pageName => &$contentList) {
      !isset(classSupernova::$sn_mvc[$arrayName][$pageName]) ? classSupernova::$sn_mvc[$arrayName][$pageName] = array() : false;
      foreach($contentList as $contentName => &$content) {
        classSupernova::$sn_mvc[$arrayName][$pageName][$contentName] = $content;
      }
    }
  }

  protected function mergeCss() { $this->mergeArraySpecial('css'); }

  protected function mergeJavascript() { $this->mergeArraySpecial('javascript'); }

  public function check_status() { }

  public static function orderModules() {
// load_order:
//  100000 - default load order
//  999999 - core_ship_constructor
//  2000000000 - that requires that all possible modules loaded already
//  2000100000 - game_skirmish

// Генерируем список требуемых модулей
    $load_order = array();
    $sn_req = array();

    foreach (sn_module::$sn_module as $loaded_module_name => $module_data) {
      $load_order[$loaded_module_name] = !empty($module_data->manifest['load_order']) ? $module_data->manifest['load_order'] : 100000;
      if (!empty($module_data->manifest['require'])) {
        foreach ($module_data->manifest['require'] as $require_name) {
          $sn_req[$loaded_module_name][$require_name] = 0;
        }
      }
    }

// pdump($load_order, '$load_order');

// Создаем последовательность инициализации модулей
// По нормальным делам надо сначала читать их конфиги - вдруг какой-то модуль отключен?
    do {
      $prev_order = $load_order;

      foreach ($sn_req as $loaded_module_name => &$req_data) {
        $level = 1;
        foreach ($req_data as $req_name => &$req_level) {
          if ($load_order[$req_name] == -1 || !isset($load_order[$req_name])) {
            $level = $req_level = -1;
            break;
          } else {
            $level += $load_order[$req_name];
          }
          $req_level = $load_order[$req_name];
        }
        if ($level > $load_order[$loaded_module_name] || $level == -1) {
          $load_order[$loaded_module_name] = $level;
        }
      }
    } while($prev_order != $load_order);

    asort($load_order);

    unset($sn_req);

// Инициализируем модули
// По нормальным делам это должна быть загрузка модулей и лишь затем инициализация - что бы минимизировать размер процесса в памяти
    foreach ($load_order as $loaded_module_name => $load_order_order) {
      if ($load_order_order >= 0) {
        sn_module::$sn_module[$loaded_module_name]->check_status();
        if (!sn_module::$sn_module[$loaded_module_name]->manifest['active']) {
          unset(sn_module::$sn_module[$loaded_module_name]);
          continue;
        }

        sn_module::$sn_module[$loaded_module_name]->initialize();
        sn_module::$sn_module_list[sn_module::$sn_module[$loaded_module_name]->manifest['package']][$loaded_module_name] = &sn_module::$sn_module[$loaded_module_name];
      } else {
        unset(sn_module::$sn_module[$loaded_module_name]);
      }
    }

// Скрипач не нужон
    unset($load_order);
  }

  /**
   * @param string $moduleName
   *
   * @return bool
   */
  public static function isModuleActive($moduleName) {
    return
      !empty(sn_module::$sn_module[$moduleName])
      &&
      sn_module::$sn_module[$moduleName] instanceof sn_module
      &&
      sn_module::$sn_module[$moduleName]->manifest['active'];
  }

  /**
   * @param string $moduleName
   *
   * @return sn_module
   */
  public static function getModule($moduleName) {
    return sn_module::$sn_module[$moduleName];
  }

}
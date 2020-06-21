<?php
/**
 * Plugin Mél Portail
 *
 * Portail web
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class mel_portail extends rcube_plugin
{
  /**
   *
   * @var string
   */
  public $task = '.*';
  /**
   * @var  rcmail The one and only instance
   */
  public $rc;
  /**
   * Array of templates
   * @var array
   */
  private $templates;
  /**
   * Array of items
   * @var array
   */
  private $items;
  
  /**
   * (non-PHPdoc)
   * @see rcube_plugin::init()
   */
  function init()
  {
    $this->rc = rcmail::get_instance();
    
    // Si tache = portail, on charge l'onglet
    if ($this->rc->task == 'portail') {
      $this->add_texts('localization/', true);
      // ajout de la tache
      $this->register_task('portail');
      // Chargement de la conf
      $this->load_config();
      // Ajout de l'interface
      include_once 'modules/imodule.php';
      include_once 'modules/module.php';
      // Index
      $this->register_action('index', array($this, 'action'));
      // Flux
      $this->register_action('flux', array($this, 'flux'));
      // Gestion des autres actions
      if (!empty($this->rc->action) && $this->rc->action != 'flux' && $this->rc->action != 'refresh' && $this->rc->action != 'index') {
        $templates = $this->rc->config->get('portail_templates_list', []);
        foreach ($templates as $type => $template) {
          if (isset($template['actions']) && isset($template['actions'][$this->rc->action])) {
            if (isset($template['php'])) {
              include_once 'modules/' . $type . '/' . $template['php'];
            }
            $this->register_action($this->rc->action, $template['actions'][$this->rc->action]);
          }
        }
      }
      else {
        // Ajout du css
        $skin_path = $this->local_skin_path();
        if ($this->rc->output->get_env('ismobile')) {
          $skin_path .= '_mobile';
        }
        $this->include_stylesheet($skin_path . '/mel_portail.css');
        // Si le panneau de droite n'est pas chargé on charge custom scrollbar
        if (!in_array('right_panel', $this->rc->config->get('plugins'))) {
          $this->include_stylesheet($this->local_skin_path() . '/jquery.mCustomScrollbar.min.css');
          $this->include_script('jquery.mCustomScrollbar.concat.min.js');
        }
        // Add handler
        $this->rc->output->add_handler('portail_items_list', array($this, 'items_list'));
      }
    }
    else if ($this->rc->task == 'settings' && !isset($_GET['_courrielleur'])) {
      $this->add_texts('localization/', true);
      // Chargement de la conf
      $this->load_config();
      // Ajout de l'interface
      include_once 'modules/imodule.php';
      include_once 'modules/module.php';
      // Activation du menu dans Mon compte
      $this->rc->output->set_env('enable_mesressources_portail', true);
      // register actions
      $this->register_action('plugin.mel_resources_portail', array($this,'resources_init'));
      $this->register_action('plugin.mel_portail_edit', array($this,'portail_edit'));
    }
  }
  
  function action() {
    // register UI objects
    $this->rc->output->add_handlers(array(
        'mel_portail_frame'    => array($this, 'portail_frame'),
    ));
    
    // Chargement du template d'affichage
    $this->rc->output->set_pagetitle($this->gettext('title'));
    $this->rc->output->send('mel_portail.mel_portail');
  }

  /**
   * Lecture d'un fichier de flux
   */
  function flux() {
    function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }
    // Récupération du nom du fichier
    $_file = rcube_utils::get_input_value('_file', rcube_utils::INPUT_GET);

    if (isset($_file)) {
      // Gestion du folder
      $folder = $this->rc->config->get('portail_flux_folder', null);
      if (!isset($folder)) {
        $folder = __DIR__ . '/rss/';
      }
      if ($this->rc->config->get('portail_flux_use_provenance', false)) {
        if (mel::is_internal()) {
          $folder .= 'intranet/';
        }
        else {
          $folder .= 'intranet/';
        }
      }
      // Gestion de l'extension
      if (endsWith($_file, '.xml')) {
        header("Content-Type: application/xml; charset=" . RCUBE_CHARSET);
      }
      else if (endsWith($_file, '.html')) {
        header("Content-Type: text/html; charset=" . RCUBE_CHARSET);
      }
      else if (endsWith($_file, '.json')) {
        header("Content-Type: application/json; charset=" . RCUBE_CHARSET);
      }
      // Ecriture du fichier
      $content = file_get_contents($folder . $_file);
      if ($content === false) {
        header('HTTP/1.0 404 Not Found');
      }
      else {
        echo $content;
      }
      exit;
    }
  }

  /**
   * Show item name in html template
   */
  public function itemname($attrib) {
    $name = "";
    if ($this->rc->output->get_env('personal_item_is_new')) {
      $name = $this->gettext('New item');
    }
    else {
      $item = $this->rc->output->get_env('personal_item');
      $name = $item['name'];
    }
    return $name;
  }
  
  /**
   * Initialisation du menu ressources pour les Applications du portail
   * 
   * Affichage du template et gestion de la sélection
   */
  public function resources_init() {
    $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
    if (isset($id)) {
      $id = driver_mel::gi()->rcToMceId($id);
      if ($id == 'new') {
        // Dans le cas d'une vignette perso on passe en édition
        $this->rc->output->set_env("personal_item_is_new", true);
        $this->rc->output->set_env("personal_item_id", $id);
        $this->rc->output->set_env("personal_item_read_only", false);
        $this->rc->output->set_env("personal_item", null);
      }
      else {
        // Vignette générique, l'utilisateur ne peut voir que les informations
        $user = driver_mel::gi()->getUser();
        $this->items = $this->getCardsConfiguration($user->dn);
        $this->items = array_merge($this->items, $this->rc->config->get('portail_items_list', []));
        $this->items = $this->mergeItems($this->items, $this->rc->config->get('portail_personal_items', []));
        // if (isset($personal_items[$id]) || $id == 'new') {
        // Dans le cas d'une vignette perso on passe en édition
        $this->rc->output->set_env("personal_item_is_new", $id == 'new');
        $this->rc->output->set_env("personal_item_id", $id);
        $this->rc->output->set_env("personal_item_read_only", !$this->items[$id]['personal']);
        $this->rc->output->set_env("personal_item", $this->items[$id]);
      }
      // register UI objects
      $this->rc->output->add_handlers(
        array(
          'itemname'    => array($this, 'itemname'),
          'item_edit'   => array(new Module('', $this), 'settings_handler'),
        )
      );
      // Ajout le javascript
      $this->include_script('edit.js');
      // Ajout des js des différents modules
      $templates = $this->rc->config->get('portail_templates_list', []);
      foreach ($templates as $type => $template) {
        // Ajoute le javascript ?
        if (isset($template['edit_js'])) {
          $this->include_script('modules/' . $type . '/' . $template['edit_js']);
        }
        // Ajout le css ?
        if (isset($template['edit_css'])) {
          $this->include_stylesheet('modules/' . $type . '/' . $template['edit_css']);
        }
      }
      $this->rc->output->send('mel_portail.portail_item_edit');
      // }
      // else {
      //   // Vignette générique, l'utilisateur ne peut voir que les informations
      //   $user = driver_mel::gi()->getUser();
      //   $this->items = $this->getCardsConfiguration($user->dn);
      //   $this->items = array_merge($this->items, $this->rc->config->get('portail_items_list', []));
        
      //   if (isset($this->items[$id])) {
      //     $item = $this->items[$id];
      //     $this->rc->output->set_env("resource_id", $id);
      //     $this->rc->output->set_env("resource_name", $item['name']);
      //     $this->rc->output->set_env("resource_type", $this->gettext($item['type']));
      //     if (isset($item['description'])) {
      //       $this->rc->output->set_env("resource_description", $item['description']);
      //     }
      //     if (isset($item['url'])) {
      //       $this->rc->output->set_env("resource_url", $item['url']);
      //     }
      //     if (isset($item['provenance'])) {
      //       $this->rc->output->set_env("resource_provenance", $this->gettext($item['provenance']));
      //     }
      //     if (isset($item['flip'])) {
      //       $this->rc->output->set_env("resource_flip", $this->gettext($item['flip'] ? 'true' : 'false'));
      //     }
      //     if (isset($item['feedUrl'])) {
      //       $this->rc->output->set_env("resource_feedurl", $item['feedUrl']);
      //     }
      //     if (isset($item['html'])) {
      //       $this->rc->output->set_env("resource_front_html", $item['html']);
      //     }
      //     if (isset($item['html_back'])) {
      //       $this->rc->output->set_env("resource_back_html", $item['html_back']);
      //     }
      //   }
      //   $this->rc->output->send('mel_portail.resource_portail');
      // }
    }
    else {
      // register UI objects
      $this->rc->output->add_handlers(
          array(
              'mel_resources_elements_list' => array($this, 'resources_elements_list'),
              'mel_resources_type_frame'    => array($this, 'mel_resources_type_frame'),
          )
      );
      $this->rc->output->set_env("resources_action", "portail");
      $this->rc->output->include_script('list.js');
      $this->include_script('settings.js');
      $this->rc->output->set_pagetitle($this->gettext('mel_moncompte.resources'));
      $this->rc->output->send('mel_portail.resources_elements');
    }
  }
  
  /**
   * Affiche la liste des éléments
   *
   * @param array $attrib
   * @return string
   */
  public function resources_elements_list($attrib) {
    // add id to message list table if not specified
    if (! strlen($attrib['id']))
      $attrib['id'] = 'rcmresourceselementslist';
    
    // Récupération des préférences de l'utilisateur
    $hidden_applications = $this->rc->config->get('hidden_applications', array());
      
    // Objet HTML
    $table = new html_table();
    $checkbox_subscribe = new html_checkbox(array('name' => '_show_resource_rc[]', 'title' => $this->rc->gettext('changesubscription'), 'onclick' => "rcmail.command(this.checked ? 'show_resource_in_roundcube' : 'hide_resource_in_roundcube', this.value, 'application')"));
    $user = driver_mel::gi()->getUser();
    
    $this->templates = $this->rc->config->get('portail_templates_list', []);
    $this->items = $this->getCardsConfiguration($user->dn);
    $this->items = array_merge($this->items, $this->rc->config->get('portail_items_list', []));
    $personal_items = $this->rc->config->get('portail_personal_items', []);
    $this->items = $this->mergeItems($this->items, $personal_items);
    // Tri des items
    uasort($this->items, [$this, 'sortItems']);
    
    foreach ($this->items as $id => $item) {
      if (!isset($this->templates[$item['type']])) {
        unset($this->items[$id]);
        continue;
      }
      $template = $this->templates[$item['type']];
      // Check if the item match the dn
      if (isset($item['dn'])) {
        $res = Module::filter_dn($user->dn, $item['dn']);
        if ($res !== true) {
          unset($this->items[$id]);
          continue;
        }
      }
      // Ajoute le php ?
      if (isset($template['php'])) {
        include_once 'modules/' . $item['type'] . '/' . $template['php'];
        $classname = ucfirst($item['type']);
        $object = new $classname($id, $this);
        if (!$object->show()) {
          unset($this->items[$id]);
          continue;
        }
      }
      $name = $item['name'];
      $class = '';
      if (isset($item['provenance'])) {
        $name .= ' (' . $this->gettext($item['provenance']) . ')';
      }
      // Item personnel
      if (isset($item['personal']) && $item['personal']) {
        $name = '[' . $this->gettext('personal') .'] ' . $name;
        $class = ' personal';
      }
      $table->add_row(array('id' => 'rcmrow' . driver_mel::gi()->mceToRcId($id), 'class' => 'portail' . $class, 'foldername' => driver_mel::gi()->mceToRcId($id)));
      $table->add('name', $name);
      $table->add('subscribed', $checkbox_subscribe->show((! isset($hidden_applications[$id]) ? $id : ''), array('value' => $id)));
    }
    // set client env
    $this->rc->output->add_gui_object('mel_resources_elements_list', $attrib['id']);
    
    return $table->show($attrib);
  }
  
  /**
   * Initialisation de la frame pour les ressources
   *
   * @param array $attrib
   * @return string
   */
  public function mel_resources_type_frame($attrib) {
    if (!$attrib['id']) {
      $attrib['id'] = 'rcmsharemeltypeframe';
    }
    
    $attrib['name'] = $attrib['id'];
    
    $this->rc->output->set_env('contentframe', $attrib['name']);
    $this->rc->output->set_env('blankpage', $attrib['src'] ? $this->rc->output->abs_url($attrib['src']) : 'program/resources/blank.gif');
    
    return $this->rc->output->frame($attrib);
  }

  /**
   * Merge entre les global items et les personal items
   * Certaines valeurs de global items peuvent être modifiées par un personal item
   */
  private function mergeItems($globalItems, $personalItems) {
    if (is_array($personalItems)) {
      // Support for non personal items
      foreach ($globalItems as $id => $item) {
        $globalItems[$id]['personal'] = false;
      }
      // Merge personal items to global items
      foreach ($personalItems as $id => $personalItem) {
        if (isset($globalItems[$id])) {
          if (!isset($globalItems[$id]['unchangeable']) || !$globalItems[$id]['unchangeable']) {
            if (isset($personalItem['hide'])) {
              $globalItems[$id]['hide'] = $personalItem['hide'];
            }
            if (isset($personalItem['order'])) {
              $globalItems[$id]['order'] = $personalItem['order'];
            }
          }
        }
        else {
          $personalItem['personal'] = true;
          $globalItems[$id] = $personalItem;
        }
      }
    }
    return $globalItems;
  }
  
  /**
  * Génération de la liste des items pour l'utilisateur courant
  * 
  * @param array $attrib Liste des paramètres de la liste
  * @return string HTML
  */
  public function items_list($attrib) {
    if (!$attrib['id']) {
      $attrib['id'] = 'portailview';
    }
    
    // Récupération des préférences de l'utilisateur
    $hidden_applications = $this->rc->config->get('hidden_applications', array());
    
    $content = "";
    $scripts_js = [];
    $scripts_css = [];
    $user = driver_mel::gi()->getUser();
    
    $this->templates = $this->rc->config->get('portail_templates_list', []);
    $this->items = $this->getCardsConfiguration($user->dn);
    $this->items = array_merge($this->items, $this->rc->config->get('portail_items_list', []));
    $this->items = $this->mergeItems($this->items, $this->rc->config->get('portail_personal_items', []));
    
    // Tri des items
    uasort($this->items, [$this, 'sortItems']);
    
    foreach ($this->items as $id => $item) {
      if (!isset($this->templates[$item['type']])) {
        unset($this->items[$id]);
        continue;
      }
      if (isset($item['show']) && $item['show'] === false) {
        unset($this->items[$id]);
        continue;
      }
      if (isset($hidden_applications[$id]) && !isset($item['show'])) {
        unset($this->items[$id]);
        continue;
      }
      if (isset($item['session']) && !isset($_SESSION[$item['session']])) {
        unset($this->items[$id]);
        continue;
      }
      if (isset($item['!session']) && isset($_SESSION[$item['!session']])) {
        unset($this->items[$id]);
        continue;
      }
      if (isset($item['provenance'])) {
        if (mel::is_internal() && $item['provenance'] == 'internet' 
            || !mel::is_internal() && $item['provenance'] == 'intranet') {
          unset($this->items[$id]);
          continue;
        }
      }
      $item['id'] = $id;
      $template = $this->templates[$item['type']];
      // Check if the item match the dn
      if (isset($item['dn'])) {
        $res = Module::filter_dn($user->dn, $item['dn']);
        if ($res !== true) {
          unset($this->items[$id]);
          continue;
        }
      }
      // Ajoute le php ?
      if (isset($template['php'])) {
        include_once 'modules/' . $item['type'] . '/' . $template['php'];
        $classname = ucfirst($item['type']);
        $object = new $classname($id, $this);
        if (!$object->show()) {
          unset($this->items[$id]);
          continue;
        }
        $object->init();
      }
      else {
        $object = new Module($id, $this);
      }
      $content .= $object->item_html(['id' => $id], $item, $user->dn);
      // Ajoute le javascript ?
      if (isset($template['js'])) {
        $scripts_js['modules/' . $item['type'] . '/' . $template['js']] = true;
      }
      // Ajoute le css ?
      if (isset($template['css'])) {
        $scripts_css['modules/' . $item['type'] . '/' . $template['css']] = true;
      }
      // Actualise l'objet
      $this->items[$id] = $item;
    }
    // Charger les scripts JS
    foreach ($scripts_js as $script => $load) {
      if ($load) {
        $this->include_script($script);
      }
    }
    // Charger les scripts CSS
    foreach ($scripts_css as $script => $load) {
      if ($load) {
        $this->include_stylesheet($script);
      }
    }
    $this->rc->output->set_env("portail_items", $this->items);
    // Ajout le javascript
    $this->include_script('mel_portail.js');
    return html::tag('section', $attrib, $content);
  }
  
  /**
   * Tri des items en fonction de l'order ou de l'order du template
   * 
   * @param array $a
   * @param array $b
   * 
   * @return number
   */
  private function sortItems($a, $b) {
    if (!isset($a['order']) && isset($this->templates[$a['type']])) {
      $a['order'] = $this->templates[$a['type']]['order'];
    }
    if (!isset($b['order']) && isset($this->templates[$b['type']])) {
      $b['order'] = $this->templates[$b['type']]['order'];
    }
    return strnatcmp($a['order'], $b['order']);
  }
  
  /**
   * Va lire la configuration des cards dans l'arborescence configuré
   * Par défault récupère la conf dans les fichiers config.json de chaque dossier
   * 
   * @param string $user_dn
   * @param string $config_file
   * @return array
   */
  private function getCardsConfiguration($user_dn, $config_file = '/config.json') {
    $configuration_path = $this->rc->config->get('portail_configuration_path', null);
    $configuration = [];
    if (isset($configuration_path)) {
      $configuration_base = $this->rc->config->get('portail_base_configuration_dn', null);
      $user_folders = explode(',', str_replace($configuration_base, '', $user_dn));
      for ($i = count($user_folders) - 1; $i >= 0; $i--) {
        $user_folder = explode('=', $user_folders[$i], 2);
        $configuration_path = $configuration_path . '/' . $user_folder[1];
        if (is_dir($configuration_path)) {
          $config_file = '/' . $user_folder[1] . '.json';
          $file = $configuration_path . $config_file;
          if (file_exists($file)) {
            $json = file_get_contents($file);
            if (strlen($json)) {
              $data = json_decode($json, true);
              if (!is_null($data)) {
                $configuration = array_merge($configuration, $data);
              }
            }
          }
        }
        else {
          // Le dir n'existe pas on sort de la boucle
          break;
        }
      }
    }
    return $configuration;
  }
  
  /**
   * Gestion de la frame
   * @param array $attrib
   * @return string
   */
  function portail_frame($attrib) {
    if (!$attrib['id'])
      $attrib['id'] = 'rcmportailframe';
      
    $attrib['name'] = $attrib['id'];
    
    $this->rc->output->set_env('contentframe', $attrib['name']);
    $this->rc->output->set_env('blankpage', $attrib['src'] ?
    $this->rc->output->abs_url($attrib['src']) : 'program/resources/blank.gif');
    
    return $this->rc->output->frame($attrib);
  }
}
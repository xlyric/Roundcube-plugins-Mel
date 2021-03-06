<?php

/**
 * Bounce
 *
 * Allow to redirect (a.k.a. "bounce") mail messages to other
 * Ticket #1485774 http://trac.roundcube.net/ticket/1485774
 *
 * @version 1.1
 * @author Denis Sobolev
 */
class bounce extends rcube_plugin
{
  public $task = 'mail';
  private $email_format_error;
  private $recipient_count;

  function init()
  {
    $rcmail = rcmail::get_instance();

    $this->register_action('plugin.bounce', array($this, 'request_action'));

    if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show')) {
      if ($rcmail->config->get('ismobile', false)) {
        $skin_path = 'skins/mel_larry_mobile';
      }
      else {
        $skin_path = $this->local_skin_path();
      }
      $this->include_stylesheet($skin_path . '/bounce.css');

      $this->include_script('bounce.js');
      $this->add_texts('localization', true);
      // Ajoute le bouton en fonction de la skin
      if ($rcmail->config->get('ismobile', false)) {
          $this->add_button(
                  array(
                      'command' => 'plugin.bounce.box',
                      'id' => 'bounce_button_mobile',
                      'title'   => 'bouncemessage',
                      'domain'  =>  $this->ID,
                      'label' => 'bounce',
                      'class' => 'button disabled ui-link ui-btn ui-corner-all ui-icon-arrow-r ui-btn-icon-left'
                  ),
                  'toolbar_mobile');
      } else {
          $this->add_button(
                  array(
                      'command' => 'plugin.bounce.box',
                      'id' => 'bounce_button',
                      'title'   => 'bouncemessage',
                      'domain'  =>  $this->ID,
                      'content' => rcube_utils::rep_specialchars_output($this->gettext('bounce'), 'html', 'strict'),
                      'class' => 'button bounce-ico disabled'
                  ),
                  'toolbar');
      }


      $this->add_hook('render_page', array($this, 'render_box'));

    }
  }

  function request_action()
  {
    $this->add_texts('localization');
    $msg_uid = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
    $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);

    $rcmail = rcmail::get_instance();

    $this->email_format_error = NULL;
    $this->recipient_count = 0;

    $message_charset = $rcmail->output->get_charset();
    $mailto = $this->rcmail_email_input_format(rcube_utils::get_input_value('_to', rcube_utils::INPUT_POST, TRUE, $message_charset), true);
    $mailcc = $this->rcmail_email_input_format(rcube_utils::get_input_value('_cc', rcube_utils::INPUT_POST, TRUE, $message_charset), true);
    $mailbcc = $this->rcmail_email_input_format(rcube_utils::get_input_value('_bcc', rcube_utils::INPUT_POST, TRUE, $message_charset), true);

    //error_log("msg_uid - $msg_uid, mailto-$mailto, mailcc-$mailcc, mailbcc-$mailbcc \n",3,"/var/log/nginx/checkmail_error.log");

    if ($this->email_format_error) {
      $rcmail->output->show_message('emailformaterror', 'error', array('email' => $this->email_format_error));
      $rcmail->output->send('iframe');
      exit;
    }

    $headers_old = $rcmail->storage->get_message_headers($msg_uid);
    $a_recipients = array();
    $a_recipients['To'] = $mailto;
    if (!empty($mailcc)) {
        $a_recipients['Cc'] = $mailcc;
    }
    if (!empty($mailbcc)) {
        $a_recipients['Bcc'] = $mailbcc;
    }

    $resent = array();
    $resent['From'] = $headers_old->to." <".$headers_old->to.">";
    $resent['To'] = $mailto;
    if (!empty($mailcc)) {
        $resent['Cc'] = $mailcc;
    }
    if (!empty($mailbcc)) {
        $resent['Bcc'] = $mailcc;
    }
    $resent['Message-Id'] = sprintf('<%s@%s>', md5(uniqid('rcmail'.mt_rand(),true)), $rcmail->config->mail_domain($_SESSION['imap_host']));
    $resent['Date'] = date('r');
    if ($rcmail->config->get('useragent')) {
      $resent['User-Agent'] = $rcmail->config->get('useragent');
    }

    foreach($resent as $k => $v){
       $resent_headers .= "Resent-$k: $v\n";
    }

    $rcmail->storage->set_folder($mbox);

    $msg_body = $rcmail->imap->get_raw_body($msg_uid);

    $headers = $rcmail->imap->get_raw_headers($msg_uid);
    $headers = $resent_headers.$headers;

    //$a_body = preg_split('/[\r\n]+$/sm', $msg_body);
    // PAMELA - Faire un explode au lieu du preg_split qui ne semble pas marcher
    $a_body = explode("\r\n\r\n", $msg_body);
    $c_body = count($a_body);
    for ($i=1, $body=''; $i <= $c_body; $body .= trim($a_body[$i])."\r\n\r\n", $i++)

    /* need decision for DKIM-Signature */
    /* $headers = preg_replace('/^DKIM-Signature/sm','Content-Description',$headers); */

    //MANTIS 0005731: Problème de from dans le plugin bounce
    $from = '';
    if (class_exists('mel')) {
      $identities = $rcmail->user->list_identities();
      // Parcour les identités pour définir celle par défaut
      foreach ($identities as $key => $identity) {
        if ($identity['uid'] == $rcmail->plugins->get_plugin('mel')->get_share_objet()) {
          $from = $identity['email'];
          $_SESSION['m2_from_identity'] = $from;
          break;
        }
      }
    }
    
    if (!is_object($rcmail->smtp)) {
      $rcmail->smtp_init(true);
    }

    //error_log($rcmail->imap->get_raw_headers($msg_uid)." $msg_uid\n",3,"/var/log/nginx/checkmail_error.log");
    
    $sent = $rcmail->smtp->send_mail($from, $a_recipients, $headers, $body);
    $smtp_response = $rcmail->smtp->get_response();
    $smtp_error = $rcmail->smtp->get_error();

    if (!$sent) {
      if ($smtp_error) {
        $rcmail->output->show_message($smtp_error['label'], 'error', $smtp_error['vars']);
      } else {
        $rcmail->output->show_message('sendingfailed', 'error');
      }
      $rcmail->output->send();
    } else {
      if ($rcmail->config->get('smtp_log')) {
        $log_entry = sprintf("User %s [%s]; Message for %s; %s",
          $rcmail->user->get_username(),
          $_SERVER['REMOTE_ADDR'],
          $mailto,
          "SMTP status: ".join("\n", $smtp_response));
          rcmail::write_log('sendmail', $log_entry);
      }
      // PAMELA - Ajout le label redirected au message
      $imap = $rcmail->imap;
      $imap->set_flag($msg_uid, 'redirected', $mbox);

      $rcmail->output->command('display_message', $this->gettext('messagebounced'), 'confirmation');
      $rcmail->output->send();
    }
  }

  function render_box($p) {
    $this->add_texts('localization');
    $rcmail = rcmail::get_instance();

    if (!$attrib['id']) {
      $attrib['id'] = 'bounce-box';
      $attrib['class'] = 'popupmenu';
    }

    $button = new html_inputfield(array('type' => 'button'));
    $submit = new html_inputfield(array('type' => 'submit'));
    $table = new html_table(array('cols' => 2, 'id' => 'form'));

    $table->add('title', html::label('_to', rcube_utils::rep_specialchars_output($rcmail->gettext('to'), 'html', 'strict')));
    $table->add('editfield', html::tag('textarea', array('spellcheck' =>'false', 'id' => '_to', 'name' => '_to', 'cols' => '50', 'rows'=> '2', 'tabindex' => '2', 'class' => 'editfield', 'onclick' => 'select_field(this)')));

    $target_url = $_SERVER['REQUEST_URI'];

    $rcmail->output->add_footer(html::div($attrib,
      $rcmail->output->form_tag(array('name' => 'bounceform', 'method' => 'post', 'action' => './', 'enctype' => 'multipart/form-data'),
        html::tag('input', array('type' => "hidden", 'name' => '_action', 'value' => 'bounce')) .
          html::div('bounce-title', rcube_utils::rep_specialchars_output($this->gettext('bouncemessage'), 'html', 'strict')) .
        html::div('bounce-body',
          $table->show() .
          html::div('buttons',
            $button->show($rcmail->gettext('close'), array('class' => 'button', 'onclick' => "$('#$attrib[id]').hide()")) . ' ' .
              $button->show(rcube_utils::rep_specialchars_output($this->gettext('bounce'), 'html', 'strict'), array('class' => 'button mainaction',
                  'onclick' => rcmail_output::JS_OBJECT_NAME . ".command('plugin.bounce.send', this.bounceform)"))
          )
        )
      )
    ));
    $rcmail->output->add_label('norecipientwarning');
    $rcmail->output->add_gui_object('bouncebox', $attrib['id']);
    $rcmail->output->add_gui_object('bounceform', 'bounceform');

    if ($rcmail->config->get('ismobile', false)) {
      $this->include_stylesheet('skins/mel_larry_mobile/bounce.css');
    }
    else {
      $this->include_stylesheet($this->local_skin_path() . '/bounce.css');
    }

    $rcmail->output->set_env('autocomplete_min_length', $rcmail->config->get('autocomplete_min_length'));
    $rcmail->output->add_gui_object('messageform', 'bounceform');
  }


  /*
   * Used modified function from steps/mail/sendmail.inc
   */
  private function rcmail_email_input_format($mailto, $count=false, $check=true)
  {
    $regexp = array('/[,;]\s*[\r\n]+/', '/[\r\n]+/', '/[,;]\s*$/m', '/;/', '/(\S{1})(<\S+@\S+>)/U');
    $replace = array(', ', ', ', '', ',', '\\1 \\2');

    // replace new lines and strip ending ', ', make address input more valid
    $mailto = trim(preg_replace($regexp, $replace, $mailto));

    $result = array();
    $items = rcube_utils::explode_quoted_string(',', $mailto);

    foreach($items as $item) {
      $item = trim($item);
      // address in brackets without name (do nothing)
      if (preg_match('/^<\S+@\S+>$/', $item)) {
        $item = idn_to_ascii($item);
        $result[] = $item;
      // address without brackets and without name (add brackets)
      } else if (preg_match('/^\S+@\S+$/', $item)) {
        $item = idn_to_ascii($item);
        $result[] = '<'.$item.'>';
      // address with name (handle name)
      } else if (preg_match('/\S+@\S+>*$/', $item, $matches)) {
        $address = $matches[0];
        $name = str_replace($address, '', $item);
        $name = trim($name);
        if ($name && ($name[0] != '"' || $name[strlen($name)-1] != '"')
            && preg_match('/[\(\)\<\>\\\.\[\]@,;:"]/', $name)) {
            $name = '"'.addcslashes($name, '"').'"';
        }
        $address = idn_to_ascii($address);
        if (!preg_match('/^<\S+@\S+>$/', $address))
          $address = '<'.$address.'>';

        $result[] = $name.' '.$address;
        $item = $address;
      } else if (trim($item)) {
        continue;
      }

      // check address format
      $item = trim($item, '<>');
      if ($item && $check && !rcube_utils::check_email($item)) {
        $this->email_format_error = $item;
        return;
      }
    }

    if ($count) {
      $this->recipient_count += count($result);
    }

    return implode(', ', $result);
  }
}
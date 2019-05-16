if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {

	  	var saved = true;
	  	if (rcmail.env.task == 'settings' && rcmail.env.action == 'plugin.mel_doubleauth') {
		  	window.onbeforeunload = function (e) {
//		  		return "mon texte de attention quand tu quittes";
		  		if (!saved) {
		  			rcmail.http_request('plugin.mel_doubleauth-removeuser');
		  		}
		  	}

			rcmail.addEventListener('responseafterplugin.mel_doubleauth-adduser', function(evt) {
				$('#2FA_secret').get(0).value = b32_encode(evt.response.code);

                console.log($('#2FA_secret').get(0).value);

                $('table tr:last').before("<tr><td width='20px'><span class='cercle'>2</span></td><td colspan='2'>" +rcmail.gettext('action_2', 'mel_doubleauth')+ '<div id="img_qr_code" style="display: visible; "></div></td></tr>');
				// add qr-code before msg_infor
				var url_qr_code_values = 'otpauth://totp/' +$('#prefs-title').html().split(/ - /)[1]+ '?secret=' +$('#2FA_secret').get(0).value +'&issuer=M2Web';
				$('table tr:last').before('<tr><td width="20px"><span class="cercle">3</span></td><td colspan="2">' +rcmail.gettext('action_3', 'mel_doubleauth')+ '</tr><tr><td></td><td colspan="2"><div id="2FA_qr_code" style="display: visible; margin-top: 10px;"></div></td></tr>');

                $('table tr:last').before("<tr><td width='20px'><span class='cercle'>4</span></td><td colspan='2'>" +rcmail.gettext('action_4', 'mel_doubleauth')+ '&nbsp;&nbsp;<input type="text" id="2FA_code_to_check" maxlength="10" onkeypress="if (event.keyCode == 13) return false;">&nbsp;&nbsp;<input type="button" class="button mainaction" id="2FA_check_code" value="'+rcmail.gettext('check_code', 'mel_doubleauth')+'"></td></tr>');

                $('table tr:last').before("<tr><td width='20px'><span class='cercle'>5</span></td><td colspan='2'>" +rcmail.gettext('action_5', 'mel_doubleauth')+ '</td></tr>');
                $('table tr:last').before('<tr><td colspan="3"><p><input type="button" class="button mainaction 2FA_save" id="rcmbtn120" onclick="return rcmail.command(\'plugin.mel_doubleauth-save\',\'\',this,event)" value="Enregistrer" disabled="disabled"></p></td></tr>');

                // ajax
                $('#2FA_check_code').click(function(){
                    url = "./?_action=plugin.mel_doubleauth-checkcode&code=" +$('#2FA_code_to_check').val();
                    $.post(url, function(data){
                            alert(data);
                            if(data == rcmail.gettext('code_ok', 'mel_doubleauth'))
                                $('.2FA_save').removeAttr('disabled');

                        });
                });

				var qrcode = new QRCode(document.getElementById("2FA_qr_code"), {
				    text: url_qr_code_values,
				    width: 200,
				    height: 200,
				    colorDark : "#000000",
				    colorLight : "#ffffff",
				    correctLevel : QRCode.CorrectLevel.L		// like charts.googleapis.com
				});
                url_qr_code_values = 'otpauth://totp/image_qr_code&issuer=M2Web';
                var qrcode = new QRCode(document.getElementById("img_qr_code"), {
				    text: url_qr_code_values,
				    width: 20,
				    height: 20,
				    colorDark : "#5882FA",
				    colorLight : "#ffffff",
				    correctLevel : QRCode.CorrectLevel.L		// like charts.googleapis.com
				});

				$('#2FA_qr_code').prop('title', '');    // enjoy the silence (qrcode.js uses text to set title)


			});

			function createCode() {
				var min = 0;
				var max = 9;
				var n = 0;
				var x = '';

				while (n < 6) {
				  n++;
				  x += Math.floor(Math.random() * (max - min)) + min;
				}
				return x;
			}
	  	}

		// populate all fields
		function setup2FAfields() {

				rcmail.http_request('plugin.mel_doubleauth-adduser');

				$('#mel_doubleauth-form :input').each(function(){
					if($(this).get(0).type == 'password') $(this).get(0).type = 'text';
				});

				$('#2FA_qr_code').slideDown();

				$("[name^='2FA_recovery_codes']").each(function() {
					$(this).get(0).value = createCode();
				});

				// disable save button. It needs check code to enabled again
				$('.2FA_save').attr('disabled','disabled').attr('title', rcmail.gettext('check_code_to_activate', 'mel_doubleauth'));

		}

		$('#2FA_activate_button').click(function(){
			setup2FAfields();
            $('#2FA_activate_button').attr('disabled','disabled');
		});

        // ajax
        $('#2FA_check_code').click(function(){
            url = "./?_action=plugin.mel_doubleauth-checkcode&code=" +$('#2FA_code_to_check').val();
            $.post(url, function(data){
                    alert(data);
                    if(data == rcmail.gettext('code_ok', 'mel_doubleauth'))
                        $('.2FA_save').removeAttr('disabled');

                });
        });

	  // to show/hide recovery_codes
	  $('#2FA_show_recovery_codes').click(function(){
		  if($("[name^='2FA_recovery_codes']")[0].type == 'text') {
			  $("[name^='2FA_recovery_codes']").each(function() {
				  $(this).get(0).type = 'password';
			  });
			  $('#2FA_show_recovery_codes').get(0).value = rcmail.gettext('show_recovery_codes', 'mel_doubleauth');
		  }
		  else {
			  $("[name^='2FA_recovery_codes']").each(function() {
				  $(this).get(0).type = 'text';
			  });
			  $('#2FA_show_recovery_codes').get(0).value = rcmail.gettext('hide_recovery_codes', 'mel_doubleauth');
		  }
	  });

	  // to show/hide qr_code
	  click2FA_change_qr_code = function(){
		  if( $('#2FA_qr_code').is(':visible') ) {
			  $('#2FA_qr_code').slideUp();
			  $(this).get(0).value = rcmail.gettext('show_qr_code', 'mel_doubleauth');
		  }
		  else {
			$('#2FA_qr_code').slideDown();
		  	$(this).get(0).value = rcmail.gettext('hide_qr_code', 'mel_doubleauth');
		  }
	  }
	  $('#2FA_change_qr_code').click(click2FA_change_qr_code);

	  // create secret
	  $('#2FA_create_secret').click(function(){
		  rcmail.http_request('plugin.mel_doubleauth-adduser');
//		  	var lock = rcmail.set_busy(true, 'loading');
//			rcmail.http_request('plugin.mel_doubleauth-adduser', lock);
	  });


      $('#2FA_desactivate_button').click(function(){
          $('#2FA_secret').get(0).value = '';
          $("[name^='2FA_recovery_codes']").each(function() {
                $(this).get(0).value = '';
            });
          $('#2FA_qr_code').parent().parent().remove();
          rcmail.http_request('plugin.mel_doubleauth-removeuser');
          saved = true;
          rcmail.gui_objects.mel_doubleauthform.submit();
      });


	function b32_encode(s) {
	    /* encodes a string s to base32 and returns the encoded string */
	    var alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

	    var parts = [];
	    var quanta= Math.floor((s.length / 5));
	    var leftover = s.length % 5;

	    if (leftover != 0) {
	       for (var i = 0; i < (5-leftover); i++) { s += '\x00'; }
	       quanta += 1;
	    }

	    for (i = 0; i < quanta; i++) {
	       parts.push(alphabet.charAt(s.charCodeAt(i*5) >> 3));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5) & 0x07) << 2)
	           | (s.charCodeAt(i*5+1) >> 6)));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5+1) & 0x3F) >> 1) ));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5+1) & 0x01) << 4)
	           | (s.charCodeAt(i*5+2) >> 4)));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5+2) & 0x0F) << 1)
	           | (s.charCodeAt(i*5+3) >> 7)));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5+3) & 0x7F) >> 2)));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5+3) & 0x03) << 3)
	           | (s.charCodeAt(i*5+4) >> 5)));
	       parts.push(alphabet.charAt( ((s.charCodeAt(i*5+4) & 0x1F) )));
	    }

	    var replace = 0;
	    if (leftover == 1) replace = 6;
	    else if (leftover == 2) replace = 4;
	    else if (leftover == 3) replace = 3;
	    else if (leftover == 4) replace = 1;

	    for (i = 0; i < replace; i++) parts.pop();
	    for (i = 0; i < replace; i++) parts.push("=");

	    return parts.join("");
	}



    // Define Variables
    var tabmel_doubleauth = $('<span>').attr('id', 'settingstabpluginmel_doubleauth').addClass('tablink');
    var button = $('<a>').attr('href', rcmail.env.comm_path + '&_action=plugin.mel_doubleauth').html(rcmail.gettext('mel_doubleauth', 'mel_doubleauth')).appendTo(tabmel_doubleauth);

    button.bind('click', function(e){ return rcmail.command('plugin.mel_doubleauth', this) });

    // Button & Register commands
    rcmail.add_element(tabmel_doubleauth, 'tabs');
    rcmail.register_command('plugin.mel_doubleauth', function() { rcmail.goto_url('plugin.mel_doubleauth') }, true);
    rcmail.register_command('plugin.mel_doubleauth-save', function() {
    	saved = true;
        rcmail.gui_objects.mel_doubleauthform.submit();
    }, true);
  });
}

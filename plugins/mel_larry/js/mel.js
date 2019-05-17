/**
 * Client script for the Mél Larry plugin
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
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
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

// Filter in contacts list
if (rcmail.env.task == 'addressbook') {
  $(document).on("keyup", 'input#quicksearchbox', function(e) {
    if (rcmail.env.action == 'plugin.annuaire') {
      var objs = $('#annuaire-list li.object');
    }
    else {
      var objs = $('#contacts-table tr.contact');
    }
    var search = $(this).val().toLowerCase();
    objs.show();
    objs.each(function() {
      if (rcmail.env.action == 'plugin.annuaire') {
        var obj = $(this).find('> span.name');
      }
      else {
        var obj = $(this).find('> td.name');
      }
      if (search.length > 1) {
        if (obj.text().toLowerCase().indexOf(search) >= 0) {
          obj.html(obj.text());
          obj.html(obj.text().replace(new RegExp(search, 'gi'), '<strong>$&</strong>'));
        }
        else {
          obj.html(obj.text());
          $(this).hide();
        }
      }
      else {
        obj.html(obj.text());
      }
    });
  });
}

window.rcmail && rcmail.addEventListener('init', function(evt) {
  if (rcmail.env.task == 'addressbook') {
    $('#directorylist-header').text(rcmail.get_label('mel_larry.allannuaires'));
    $('<li class="legend">' + rcmail.get_label('mel_larry.personalannuaire') + '</li>').insertAfter( "#directorylist li.all" );
  }
	if (rcmail.env.task == 'addressbook' && rcmail.env.action == 'plugin.annuaire') {
		$('#quicksearchbox').attr('placeholder', rcmail.get_label('mel_larry.search_in') + ' ' + $('#directorylist li.addressbook.selected > a').text());
	}
	else if (rcmail.env.task == 'calendar') {
		$('#quicksearchbox').attr('placeholder', rcmail.get_label('mel_larry.search_in_calendars'));
	}
	else if (rcmail.env.task == 'tasks') {
		$('#quicksearchbox').attr('placeholder', rcmail.get_label('mel_larry.search_in_tasks'));
	}
	else if (rcmail.env.task == 'settings') {
		var disconnect = $('<div>');
		disconnect.attr('class', 'disconnect');
		var disconnect_link = $('<a>');
		disconnect_link.attr('href', './?_task=logout');
		disconnect_link.attr('onclick', 'return rcmail.command(\'switch-task\',\'logout\',this,event)');
		disconnect_link.text(rcmail.get_label('right_panel.logout'))
		disconnect.append(disconnect_link);
		$('#settings-sections').append(disconnect);
	}
});

// Change skin after some actions
rcmail.addEventListener('actionafter', function(props) {
	if (props.action == 'list'
			&& rcmail.env.task == 'mail') {
		if ($('#searchmenu-menu #s_scope_all').is(':checked')) {
			$('#quicksearchbox').attr('placeholder', rcmail.get_label('mel_larry.search_in_all_folders'));
		}
		else {
			$('#quicksearchbox').attr('placeholder', rcmail.get_label('mel_larry.search_in') + ' ' + $('#folderlist-content #mailboxlist li.mailbox.selected > a').clone().children().remove().end().text());
		}
	}
	else if (props.action == 'list'
			&& rcmail.env.task == 'addressbook') {
		rcmail.set_searchscope('list');
	  var text = $('#directorylist li.addressbook.selected > a').clone().children().remove().end().text();
		$('#quicksearchbox').attr('placeholder', rcmail.get_label('mel_larry.search_in') + ' ' + text);
	}
});

// After search addressbook for all reform
rcmail.addEventListener('responseaftersearch', function(props) {
  if (rcmail.env.task == 'addressbook' && (props.response.env.source == "" || !props.response.env.source)) {
    var sources = new Object();
    $('#contacts-table tr').each(function() {
      var source = $(this).attr('id').split(/-(.+)/)[1];
      if (source && !sources[source]) {
        sources[source] = $(this).attr('id');
      }
    });
    var first = true;
    for (var source in sources) {
      var label = rcmail.get_label('mel_larry.foundin') + ' ' + $('#directorylist li#rcmli' + rcmail.html_identifier_encode(source) + ' > a').clone().children().remove().end().text();
      if (!first) {
        $('<tr class="space"><td>&nbsp;</td></tr>').insertBefore('tr#' + sources[source]);
      }
      $('<tr class="legend"><td>' + label + '</td></tr>').insertBefore('tr#' + sources[source]);
      first = false;
    }
  }
});
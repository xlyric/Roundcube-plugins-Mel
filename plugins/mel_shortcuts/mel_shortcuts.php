<?php
/**
 * Mél keyboard shortcuts
 *
 * Based on Kolab keyboard shortcuts plugin by Aleksander Machniak <machniak@kolabsys.com>
 *
 * @author Thomas Payen <thomas.payen@i-carre.net>
 *
 * Copyright (C) 2016, PNE Annuaire et Messagerie/MEDDE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class mel_shortcuts extends rcube_plugin
{
  // all task excluding 'login' and 'logout'
  public $task = '?(?!login|logout).*';
  // we've got no ajax handlers
  public $noajax = true;


  function init()
  {
    $this->include_script('mel_shortcuts.js');
  }
}

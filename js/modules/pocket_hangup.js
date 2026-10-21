/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    js/modules/pocket_hangup.js
 * \ingroup reedcrm
 * \brief   Place the Pocket hang up chip under the relaunch block of a card for module ReedCRM
 */

'use strict';

if (!window.reedcrm) {
  window.reedcrm = {};
}

/**
 * The chip is printed server side next to the title of the banner, because that is the only slot a
 * card offers. The relaunch block it has to sit under is printed hidden at the bottom of the page
 * and teleported into the header by its own script, so the final order can only be settled here,
 * once that block has landed. A card without relaunch block keeps the chip next to its title.
 */
window.reedcrm.pocketHangup = {

  // The relaunch block is teleported on a timer, give it room without watching the page forever
  watchDuration: 5000,

  init: function() {
    if (!window.reedcrm.pocketHangup.chip()) {
      return;
    }

    if (window.reedcrm.pocketHangup.place()) {
      return;
    }

    window.reedcrm.pocketHangup.watch();
  },

  /**
   * The chip, as long as it still is where the server printed it.
   *
   * @return {Element|null} Chip to move, null when there is none or it was already moved.
   */
  chip: function() {
    var chip = document.querySelector('a.reedcrm-pocket-hangup-button');

    return chip && !chip.closest('.reedcrm-pocket-hangup-row') ? chip : null;
  },

  /**
   * The relaunch block, once it left the hidden row it is printed in.
   *
   * @return {Element|null} Block the chip goes under, null while it has not landed.
   */
  relaunchBlock: function() {
    var block = document.querySelector('.reedcrm-header-relaunch-master');

    return block && !block.closest('#reedcrm-relaunch-row-hidden') ? block : null;
  },

  /**
   * Move the chip under the relaunch block.
   *
   * @return {boolean} True once the chip was moved, false while the block is not there yet.
   */
  place: function() {
    var block = window.reedcrm.pocketHangup.relaunchBlock();
    var chip  = window.reedcrm.pocketHangup.chip();

    if (!block || !chip) {
      return false;
    }

    // The teleport drops the block either in a wrapper of its own or in the flex row of the header
    // blocks, and the chip belongs under the whole row, not under one of its blocks
    var host = block.parentElement;
    var row  = document.createElement('div');

    row.className = 'reedcrm-pocket-hangup-row';
    row.appendChild(chip);
    host.insertAdjacentElement('afterend', row);

    return true;
  },

  /**
   * Wait for the relaunch block to be teleported, then place the chip.
   *
   * @return {void}
   */
  watch: function() {
    var observer = new MutationObserver(function() {
      if (window.reedcrm.pocketHangup.place()) {
        observer.disconnect();
      }
    });

    observer.observe(document.body, {childList: true, subtree: true});

    window.setTimeout(function() {
      observer.disconnect();
    }, window.reedcrm.pocketHangup.watchDuration);
  }

};

$(document).ready(function() {
  window.reedcrm.pocketHangup.init();
});

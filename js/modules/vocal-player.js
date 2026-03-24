/* Copyright (C) 2021-2025 EVARISK <technique@evarisk.com>
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
 * \file    js/modules/vocal-player.js
 * \ingroup reedcrm
 * \brief   JavaScript vocal player for module ReedCRM
 */

'use strict';

if (!window.reedcrm) {
  window.reedcrm = {};
}

window.reedcrm.vocalPlayer = {

  formatTime: function(seconds) {
    var s = Math.floor(seconds) || 0;
    var m = Math.floor(s / 60);
    s = s % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  },

  stop: function($btn) {
    var audio    = $btn.data('rc-audio');
    var interval = $btn.data('rc-interval');
    if (audio && !audio.paused) { audio.pause(); }
    if (interval) { clearInterval(interval); $btn.removeData('rc-interval'); }
    $btn.find('i').removeClass('fa-pause').addClass('fa-play');
    $btn.removeClass('playing');
  },

  init: function() {
    $(document).on('click', '.reedcrm-vocal-player', function() {
      var $btn     = $(this);
      var url      = $btn.data('audio-url');
      var audioObj = $btn.data('rc-audio');
      var $time    = $btn.find('.reedcrm-vocal-time');

      // Stop all other active players
      $('.reedcrm-vocal-player').not($btn).each(function() {
        window.reedcrm.vocalPlayer.stop($(this));
      });

      // Create audio object on first click
      if (!audioObj) {
        audioObj = new Audio(url);
        $btn.data('rc-audio', audioObj);
        audioObj.addEventListener('ended', function() {
          window.reedcrm.vocalPlayer.stop($btn);
        });
      }

      if (audioObj.paused) {
        audioObj.play();
        $btn.find('i').removeClass('fa-play').addClass('fa-pause');
        $btn.addClass('playing');
        var interval = setInterval(function() {
          var cur = window.reedcrm.vocalPlayer.formatTime(audioObj.currentTime);
          var dur = isNaN(audioObj.duration) ? '--:--' : window.reedcrm.vocalPlayer.formatTime(audioObj.duration);
          $time.text(cur + ' / ' + dur);
        }, 500);
        $btn.data('rc-interval', interval);
      } else {
        window.reedcrm.vocalPlayer.stop($btn);
      }
    });
  }

};

window.reedcrm.vocalPlayer.init();

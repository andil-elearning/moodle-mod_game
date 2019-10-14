<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_game;
defined('MOODLE_INTERNAL') || die();

class observer {
    public static function glossary_updated(\mod_glossary\event\entry_deleted $event) {
        global $DB;

        $gamekindlist = "'snakes', 'sudoku', 'hiddenpicture'";
        $games = $DB->get_records_sql("
            select *
            from {game}
            where glossaryid = $event->objectid
            and gamekind in ($gamekindlist)"
        );
        if ($games) {
            foreach ($games as $game) {
                $attemps = $DB->get_records('game_attempts', ['gameid' => $game->id, 'timefinish' => 0]);
                foreach ($attemps as $attemp) {
                    $attemp->timefinish = time();
                    $attemp->timelastattempt = time();
                    $attemp->attemps = (($attemp->attemps != null) ? $attemp->attemps + 1 : 1);
                    $DB->update_record('game_attempts', $attemp);
                }
            }
        }
    }
}
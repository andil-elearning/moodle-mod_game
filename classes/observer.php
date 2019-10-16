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
    public static function glossary_updated(\block_wok_connector\event\glossary_update $event) {
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
                $attempts = $DB->get_records('game_attempts', ['gameid' => $game->id, 'timefinish' => 0]);
                foreach ($attempts as $attempt) {
                    $attempt->timefinish = time();
                    $attempt->timelastattempt = $attempt->timelastattempt ? $attempt->timelastattempt : time();
                    $attempt->attempts = $attempt->attempts ? $attempt->attempts + 1 : 1;
                    $DB->update_record('game_attempts', $attempt);
                }
            }
        }
    }
}
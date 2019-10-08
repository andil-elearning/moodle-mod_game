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


/**
 * This file plays the game Hidden Picture.
 *
 * @package    mod_game
 * @copyright  2007 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Plays the game "Hidden picture"
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param stdClass $context
 * @param stdClass $course
 */
function game_hiddenpicture_continue( $cm, $game, $attempt, $hiddenpicture, $context, $course) {
    global $DB, $USER;

    if ($attempt != false and $hiddenpicture != false) {
        // Continue a previous attempt.
        return game_hiddenpicture_play( $cm, $game, $attempt, $hiddenpicture, false, $context, $course);
    }

    if ($attempt == false) {
        // Start a new attempt.
        $attempt = game_addattempt( $game);
    }

    $cols = $game->param1;
    $rows = $game->param2;
    if ($cols == 0) {
        print_error( get_string( 'hiddenpicture_nocols', 'game'));
    }
    if ($rows == 0) {
        print_error( get_string( 'hiddenpicture_norows', 'game'));
    }

    // New attempt.
    $n = $game->param1 * $game->param2;
    $recs = game_questions_selectrandom( $game, CONST_GAME_TRIES_REPETITION * $n);
    $selectedrecs = game_select_from_repetitions( $game, $recs, $n);

    $newrec = game_hiddenpicture_selectglossaryentry( $game, $attempt);

    if ($recs === false) {
        print_error( get_string( 'no_questions', 'game'));
    }

    $positions = array();
    $pos = 1;
    for ($col = 0; $col < $cols; $col++) {
        for ($row = 0; $row < $rows; $row++) {
            $positions[] = $pos++;
        }
    }
    $i = 0;
    $field = ($game->sourcemodule == 'glossary' ? 'glossaryentryid' : 'questionid');
    foreach ($recs as $rec) {
        if ($game->sourcemodule == 'glossary') {
            $key = $rec->glossaryentryid;
        } else {
            $key = $rec->questionid;
        }

        if (!array_key_exists( $key, $selectedrecs)) {
            continue;
        }

        $query = new stdClass();
        $query->attemptid = $newrec->id;
        $query->gamekind = $game->gamekind;
        $query->gameid = $game->id;
        $query->userid = $USER->id;

        $pos = array_rand( $positions);
        $query->mycol = $positions[ $pos];
        unset( $positions[ $pos]);

        $query->sourcemodule = $game->sourcemodule;
        $query->questionid = $rec->questionid;
        $query->glossaryentryid = $rec->glossaryentryid;
        $query->score = 0;
        if (($query->id = $DB->insert_record( 'game_queries', $query)) == 0) {
            print_error( 'error inserting in game_queries');
        }
        game_update_repetitions($game->id, $USER->id, $query->questionid, $query->glossaryentryid);
    }

    game_hiddenpicture_play( $cm, $game, $attempt, $newrec, false, $context, $course);
}


/**
 * Create the game_hiddenpicture record.
 *
 * @param stdClass $game
 * @param stdClass $attempt
 */
function game_hiddenpicture_selectglossaryentry( $game, $attempt) {
    global $CFG, $DB, $USER;

    srand( (double)microtime() * 1000000);

    if ($game->glossaryid2 == 0) {
        print_error( get_string( 'must_select_glossary', 'game'));
    }
    $select = "ge.glossaryid={$game->glossaryid2}";
    $table = '{glossary_entries} ge';
    if ($game->glossarycategoryid2) {
        $table .= ",{glossary_entries_categories} gec";
        $select .= " AND gec.entryid = ge.id AND gec.categoryid = {$game->glossarycategoryid2}";
    }
    if ($game->param7 == 0) {
        // Allow spaces.
        $select .= " AND concept NOT LIKE '% %'";
    }

    $sql = "SELECT ge.id,attachment FROM $table WHERE $select";
    if (($recs = $DB->get_records_sql( $sql)) == false) {
        $a = new stdClass();
        $a->name = "'".$DB->get_field('glossary', 'name', array( 'id' => $game->glossaryid2))."'";
        print_error( get_string( 'hiddenpicture_nomainquestion', 'game', $a));
        return false;
    }
    $ids = array();
    $keys = array();
    $fs = get_file_storage();
    $cmg = get_coursemodule_from_instance('glossary', $game->glossaryid2, $game->course);
    $context = game_get_context_module_instance( $cmg->id);
    foreach ($recs as $rec) {
        $files = $fs->get_area_files($context->id, 'mod_glossary', 'attachment', $rec->id, "timemodified", false);
        if ($files) {
            foreach ($files as $key => $file) {
                $s = strtoupper( $file->get_filename());
                $s = substr( $s, -4);
                if ($s == '.GIF' or $s == '.JPG' or $s == '.PNG') {
                    $ids[] = $rec->id;
                    $keys[] = $file->get_pathnamehash();
                }
            }
        }
    }
    if (count( $ids) == 0) {
        $a = new stdClass();
        $a->name = "'".$DB->get_field( 'glossary', 'name', array( 'id' => $game->glossaryid2))."'";
        print_error( get_string( 'hiddenpicture_nomainquestion', 'game', $a));
        return false;
    }

    // Have to select randomly one glossaryentry.
    $poss = array();
    for ($i = 0; $i < count($ids); $i++) {
        $poss[] = $i;
    }
    shuffle( $poss);
    $minnum = 0;
    $attachement = '';
    for ($i = 0; $i < count($ids); $i++) {
        $pos = $poss[ $i];
        $tempid = $ids[ $pos];
        $a = array( 'gameid' => $game->id, 'userid' => $USER->id, 'questionid' => 0, 'glossaryentryid' => $tempid);
        if (($rec2 = $DB->get_record('game_repetitions', $a, 'id,repetitions r')) != false) {
            if (($rec2->r < $minnum) or ($minnum == 0)) {
                $minnum = $rec2->r;
                $glossaryentryid = $tempid;
                $attachement = $keys[ $pos];
            }
        } else {
            $glossaryentryid = $tempid;
            $attachement = $keys[ $pos];
            break;
        }
    }

    $sql = 'SELECT id, concept as answertext, definition as questiontext,'.
        ' id as glossaryentryid, 0 as questionid, glossaryid, attachment'.
        ' FROM {glossary_entries} WHERE id = '.$glossaryentryid;
    if (($rec = $DB->get_record_sql( $sql)) == false) {
        return false;
    }
    $query = new stdClass();
    $query->attemptid = $attempt->id;
    $query->gamekind = $game->gamekind;
    $query->gameid = $game->id;
    $query->userid = $USER->id;

    $query->mycol = 0;
    $query->sourcemodule = 'glossary';
    $query->questionid = 0;
    $query->glossaryentryid = $rec->glossaryentryid;
    $query->attachment = $attachement;
    $query->questiontext = $rec->questiontext;
    $query->answertext = $rec->answertext;
    $query->score = 0;
    if (($query->id = $DB->insert_record( 'game_queries', $query)) == 0) {
        print_error( 'Error inserting in game_queries');
    }
    $newrec = new stdClass();
    $newrec->id = $attempt->id;
    $newrec->correct = 0;
    if (!game_insert_record(  'game_hiddenpicture', $newrec)) {
        print_error( 'Error inserting in game_hiddenpicture');
    }

    game_update_repetitions($game->id, $USER->id, $query->questionid, $query->glossaryentryid);

    return $newrec;
}

/**
 * Plays the game "Hidden picture"
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param boolean $showsolution
 * @param stdClass $context
 */
function game_hiddenpicture_play( $cm, $game, $attempt, $hiddenpicture, $showsolution, $context) {
    if ($game->toptext != '') {
        echo $game->toptext.'<br>';
    }

    // Show picture.
    $offsetquestions = game_hiddenpicture_compute_offsetquestions( $game->sourcemodule, $attempt, $numbers, $correctquestions);
    unset( $offsetquestions[ 0]);

    game_hiddenpicture_showhiddenpicture( $cm->id, $game, $attempt, $hiddenpicture, $showsolution,
        $offsetquestions, $correctquestions);

    // Show questions.
    $onlyshow = false;
    $showsolution = false;

    switch ($game->sourcemodule) {
        case 'quiz':
        case 'question':
            game_hiddenpicture_showquestions_quiz( $cm->id, $game, $attempt, $hiddenpicture, $offsetquestions,
                $numbers, $correctquestions, $onlyshow, $showsolution, $context);
            break;
        case 'glossary':
            game_hiddenpicture_showquestions_glossary( $cm->id, $game, $attempt, $hiddenpicture,
                $offsetquestions, $numbers, $correctquestions, $onlyshow, $showsolution);
            break;
    }

    if ($game->bottomtext != '') {
        echo '<br><br>'.$game->bottomtext;
    }
}

/**
 * Get glossary entries
 *
 * @param stdClass $game
 * @param int $offsetentries
 * @param string $entrylist
 * @param int $numbers
 */
function game_hiddenpicture_getglossaryentries( $game, $offsetentries, &$entrylist, $numbers) {
    global $DB;

    $entrylist = implode( ',', $offsetentries);

    if ($entrylist == '') {
        print_error( get_string( 'hiddenpicture_noentriesfound', 'game'));
    }

    // Load the questions.
    if (!$entries = $DB->get_records_select( 'glossary_entries', "id IN ($entrylist)")) {
        print_error( get_string('hiddenpicture_noentriesfound', 'game'));
    }

    return $entries;
}
/**
 * Show the hiddenpicture and glossaryentries.
 *
 * @param int $id
 * @param string $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param int $offsetentries
 * @param int $numbers
 * @param int $correctentries
 * @param boolean $onlyshow
 * @param boolean $showsolution
 */
function game_hiddenpicture_showquestions_glossary( $id, $game, $attempt, $hiddenpicture, $offsetentries, $numbers,
 $correctentries, $onlyshow, $showsolution) {
    global $CFG, $DB;
    $entries = game_hiddenpicture_getglossaryentries( $game, $offsetentries, $questionlist, $numbers);

    // I will sort with the number of each question.
    $entries2 = array();
    foreach ($entries as $q) {
        $ofs = $numbers[ $q->id];
        $entries2[ $ofs] = $q;
    }
    ksort( $entries2);

    if (count( $entries2) == 0) {
        game_hiddenpicture_showquestion_onfinish( $id, $game, $attempt, $hiddenpicture);
        return;
    }

    // Start the form.
    echo "<br><form id=\"responseform\" method=\"post\" ".
        "action=\"{$CFG->wwwroot}/mod/game/attempt.php\" onclick=\"this.autocomplete='off'\">\n";

    if ($onlyshow) {
        $hasquestions = false;
    } else {
        $hasquestions = ( count($correctentries) < count( $entries2));
    }

    if ($hasquestions) {
        echo "<input type=\"submit\" name=\"submit\" value=\"".get_string('hiddenpicture_submit', 'game')."\">";
    }

    // Add a hidden field with the quiz id.
    echo '<div>';
    echo '<input type="hidden" name="id" value="' . s($id) . "\" />\n";
    echo '<input type="hidden" name="action" value="hiddenpicturecheckgl" />';

    $query = $DB->get_record_select( 'game_queries', "attemptid=$hiddenpicture->id AND mycol=0",
    null, 'id,glossaryentryid,attachment,questiontext');

    echo '<input type="hidden" name="queryid" value="' . $query->id . "\" />";

    // Add a hidden field with glossaryentryid.
    echo '<input type="hidden" name="glossaryentryid" value="'.$query->glossaryentryid."\" />";

    // Print all the questions.

    // Add a hidden field with questionids.
    echo '<input type="hidden" name="questionids" value="'.$questionlist."\" />\n";

    $number = 0;
    foreach ($entries2 as $entry) {
        $ofs = $numbers[ $entry->id];
        if (array_key_exists( $ofs, $correctentries)) {
            continue;   // I don't show the correct answers.
        }

        $query = new StdClass;
        $query->glossaryid = $game->glossaryid;
        $query->glossaryentryid = $entry->id;
        $s = '<div class= "questionline"> <span class="questionnumber"> A'.$ofs.'.</span> '.game_show_query( $game, $query, $entry->definition, 0) . "</div>";
        $s .= '<div class="answerline">';
        if ($showsolution) {
            $s .= '<span class="answer">' . get_string( 'answer').': </span>';
            $s .= "<input type=\"text\" name=\"resp{$entry->id}\" value=\"$entry->concept\"size=30 /><br>";
        } else if ($onlyshow === false) {
            $s .= get_string( 'answer').': ';
            $s .= "<input type=\"text\" name=\"resp{$entry->id}\" size=30 /><br>";
        }
        $s .= '</div>';
        echo $s."<hr>";
    }

    echo "</div>";

    // Finish the form.
    if ($hasquestions) {
        echo "<input class=\"submitgame\" type=\"submit\" name=\"submit\" value=\"".get_string('hiddenpicture_submit', 'game')."\">";
    }

    echo "</form>\n";
}
/**
 * Returns a map with an offset and id of each question.
 *
 * @param string $sourcemodule
 * @param stdClass $attempt
 * @param int $numbers
 * @param int $correctquestions
 */
function game_hiddenpicture_compute_offsetquestions( $sourcemodule, $attempt, &$numbers, &$correctquestions) {
    global $CFG, $DB;

    $offsetquestions = array();
    if ($attempt == null) {
        return $offsetquestions;
    }
    $select = "attemptid = $attempt->id";

    $fields = 'id, mycol, score';
    switch( $sourcemodule)
    {
        case 'quiz':
        case 'question':
            $fields .= ',questionid as id2';
            break;
        case 'glossary':
            $fields .= ',glossaryentryid as id2';
            break;
    }
    if (($recs = $DB->get_records_select( 'game_queries', $select, null, '', $fields)) == false) {
        $DB->execute( "DELETE FROM {$CFG->prefix}game_hiddenpicture WHERE id={$attempt->id}");
        print_error( 'There are no questions '.$attempt->id);
    }

    $numbers = array();
    $correctquestions = array();
    foreach ($recs as $rec) {
        $offsetquestions[ $rec->mycol] = $rec->id2;
        $numbers[ $rec->id2] = $rec->mycol;
        if ( $rec->score == 1) {
            $correctquestions[ $rec->mycol] = 1;
        }
    }

    ksort( $offsetquestions);

    return $offsetquestions;
}


/**
 * Get question list
 *
 * @param int $offsetquestions
 */
function game_hiddenpicture_getquestionlist( $offsetquestions) {
    $questionlist = '';
    foreach ($offsetquestions as $q) {
        if ($q != 0) {
            $questionlist .= ','.$q;
        }
    }
    $questionlist = substr( $questionlist, 1);

    if ($questionlist == '') {
        print_error( get_string('no_questions', 'game'));
    }

    return $questionlist;
}

/**
 * get questions for hiddenpicture
 *
 * @param string $questionlist
 */
function game_hiddenpicture_getquestions( $questionlist) {
    global $CFG, $DB;

    // Load the questions.
    $sql = "SELECT q.*,qmo.single ".
        " FROM {$CFG->prefix}question ".
            " LEFT JOIN {$CFG->prefix}qtype_multichoice_options qmo ON q.id=qmo.questionid AND q.qtype='multichoice' ".
        " WHERE q.id IN ($questionlist)";
    if (!$questions = $DB->get_records_select( 'question', "id IN ($questionlist)")) {
        print_error( get_string( 'no_questions', 'game'));
    }

    // Load the question type specific information.
    if (!get_question_options($questions)) {
        print_error('Could not load question options');
    }

    return $questions;
}

/**
 * Show question onfinish
 *
 * @param int $id
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 */
function game_hiddenpicture_showquestion_onfinish( $id, $game, $attempt, $hiddenpicture) {
    global $CFG;
    echo '<div class="hiddenpicture_win"' . get_string( 'win', 'game') . '</div>';
    echo '<br>';
    echo "<a href=\"{$CFG->wwwroot}/mod/game/attempt.php?id=$id\">".
        get_string( 'nextgame', 'game').'</a> &nbsp; &nbsp; &nbsp; &nbsp; ';
    echo "<a class='endgamebutton' href=\"{$CFG->wwwroot}?id=$id\">".get_string( 'finish', 'game').'</a> ';
}

/**
 * Plays the game hiddenpicture
 *
 * @param int $id
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param int $offsetquestions
 * @param string $numbers
 * @param int $correctquestions
 * @param boolean $onlyshow
 * @param boolean $showsolution
 * @param stdClass $context
 */
function game_hiddenpicture_showquestions_quiz( $id, $game, $attempt, $hiddenpicture, $offsetquestions, $numbers,
     $correctquestions, $onlyshow, $showsolution, $context) {
    global $CFG;

    $questionlist = game_hiddenpicture_getquestionlist( $offsetquestions);
    $questions = game_hiddenpicture_getquestions( $questionlist);

    // I will sort with the number of each question.
    $questions2 = array();
    foreach ($questions as $q) {
        $ofs = $numbers[ $q->id];
        $questions2[ $ofs] = $q;
    }
    ksort( $questions2);

    if (count( $questions2) == 0) {
        game_hiddenpicture_showquestion_onfinish( $id, $game, $attempt, $hiddenpicture);
        return;
    }

    $number = 0;
    $found = false;
    foreach ($questions2 as $question) {
        $ofs = $numbers[ $question->id];
        if (array_key_exists( $ofs, $correctquestions)) {
            continue;   // I don't show the correct answers.
        }

        if ( $found == false) {
            $found = true;
            // Start the form.
            echo "<form id=\"responseform\" method=\"post\" ".
                "action=\"{$CFG->wwwroot}/mod/game/attempt.php\" onclick=\"this.autocomplete='off'\">";

            // Add a hidden field with the quiz id.
            echo '<div>';
            echo '<input type="hidden" name="id" value="' . s($id) . "\" />";
            echo '<input type="hidden" name="action" value="hiddenpicturecheck" />';

            // Print all the questions.

            // Add a hidden field with questionids.
            echo '<input type="hidden" name="questionids" value="'.$questionlist."\" />";

        }
        echo '<div class="questiondiv">';

        $number = "<span class='questionnumber'>A$ofs</span>";
        echo $number;
        echo "<input type=\"submit\" name=\"submit\" class=\"hiddenpicture_submit\" value=\"".get_string('hiddenpicture_submit', 'game')."\">";
        game_print_question( $game, $question, $context);
        echo '</div>';

    }

    if ($found) {
        echo "</div>";

        // Finish the form.
        echo '</div>';

        echo "</form>\n";
    }
}


/**
 * "Hidden picture" compute score
 *
 * @param stdClass $game
 * @param stdClass $hiddenpicture
 */
function game_hidden_picture_computescore( $game, $hiddenpicture) {
    $correct = $hiddenpicture->correct;
    if ($hiddenpicture->found) {
        $correct++;
    }
    $remaining = $game->param1 * $game->param2 - $hiddenpicture->correct;
    $div2 = $correct + $hiddenpicture->wrong + $remaining;
    if ($hiddenpicture->found) {
        $percent = ($correct + $remaining) / $div2;
    } else {
        $percent = $correct / $div2;
    }

    return $percent;
}

/**
 * Show hidden picture
 *
 * @param int $id
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param boolean $showsolution
 * @param int $offsetquestions
 * @param int $correctquestions
 */
function game_hiddenpicture_showhiddenpicture( $id, $game, $attempt, $hiddenpicture, $showsolution,
            $offsetquestions, $correctquestions) {
    global $DB, $CFG;

    $foundcells = '';
    foreach ($correctquestions as $key => $val) {
        $foundcells .= ','.$key;
    }
    $cells = '';
    foreach ($offsetquestions as $key => $val) {
        if ($key != 0) {
            $cells .= ','.$key;
        }
    }

    $query = $DB->get_record_select( 'game_queries', "attemptid=$hiddenpicture->id AND mycol=0",
        null, 'id,glossaryentryid,attachment,questiontext');

    echo "<form id=\"responseform\" method=\"post\" ".
    "action=\"{$CFG->wwwroot}/mod/game/attempt.php\" onclick=\"this.autocomplete='off'\">";
    echo "<input type=\"submit\" name=\"finishattempt\" value=\"".
    get_string('hiddenpicture_finishattemptbutton', 'game')."\">";

    // Add a hidden field with the quiz id.
    echo '<input type="hidden" name="id" value="' . s($id) . "\" />";
    echo '<input type="hidden" name="action" value="hiddenpicturecheck" />';
    echo "</form>";

    // Grade.
    echo "<span class='str_grade'/>".get_string( 'grade', 'game').' : '.round( $attempt->score * 100).' % </span>';

    game_hiddenpicture_showquestion_glossary( $game, $id, $query);

    $cells = substr( $cells, 1);
    $foundcells = substr( $foundcells, 1);
    game_showpicture( $id, $game, $attempt, $query, $cells, $foundcells, true);
}

/**
 * hidden picture. show question glossary
 *
 * @param stdClass $game
 * @param int $id
 * @param stdClass $query
 */
function game_hiddenpicture_showquestion_glossary( $game, $id, $query) {
    global $CFG, $DB;

    $entry = $DB->get_record( 'glossary_entries', array( 'id' => $query->glossaryentryid));

    // Start the form.
    echo '<br>';
    echo "<form method=\"post\" ".
        "action=\"{$CFG->wwwroot}/mod/game/attempt.php\" onclick=\"this.autocomplete='off'\">";
    echo "<input type=\"submit\" class=\"hiddenpicture_finishattempt\" name=\"finishattempt\" ".
        "value=\"".get_string('hiddenpicture_mainsubmit', 'game')."\">";

    // Add a hidden field with the queryid.
    echo '<input type="hidden" name="id" value="' . s($id) . "\" />";
    echo '<input type="hidden" name="action" value="hiddenpicturecheckg" />';
    echo '<input type="hidden" name="queryid" value="' . $query->id . "\" />";

    // Add a hidden field with glossaryentryid.
    echo '<input type="hidden" name="glossaryentryid" value="'.$query->glossaryentryid."\" />";

    $temp = $game->glossaryid;
    $game->glossaryid = $game->glossaryid2;
    echo '<span class="hiddenpicture_question">' . game_show_query( $game, $query, $entry->definition) . '</span>';
    $game->glossaryid = $temp;

    echo '<label for="answer"> ' . get_string( 'answer').': </label>';
    echo "<input type=\"text\" name=\"answer\" size=30 /><br>";

    echo "</form><br>";
}

/**
 * Check main question
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param boolean $finishattempt
 * @param stdClass $context
 * @param stdClass $course
 */
function game_hiddenpicture_check_mainquestion( $cm, $game, &$attempt, &$hiddenpicture, $finishattempt, $context, $course) {
    global $CFG, $DB;

    $responses = data_submitted();
    $glossaryentryid = $responses->glossaryentryid;
    $queryid = $responses->queryid;

    // Load the glossary entry.
    if (!($entry = $DB->get_record( 'glossary_entries', array( 'id' => $glossaryentryid)))) {
        print_error( get_string( 'noglossaryentriesfound', 'game'));
    }
    $answer = $responses->answer;
    $correct = false;
    if ($answer != '') {
        if (game_upper( $entry->concept) == game_upper( $answer)) {
            $correct = true;
        }
    }

    // Load the query.
    if (!($query = $DB->get_record( 'game_queries', array( 'id' => $queryid)))) {
        print_error( "The query $queryid not found");
    }

    game_update_queries( $game, $attempt, $query, $correct, $answer);
    if ($correct) {
        $hiddenpicture->found = 1;
    } else {
        $hiddenpicture->wrong++;
    }
    if (!$DB->update_record( 'game_hiddenpicture', $hiddenpicture)) {
        print_error( 'game_hiddenpicture_check_mainquestion: error updating in game_hiddenpicture');
    }

    $score = game_hidden_picture_computescore( $game, $hiddenpicture);
    game_updateattempts( $game, $attempt, $score, $correct, $cm, $course);

    if ($correct == false) {
        game_hiddenpicture_play( $cm, $game, $attempt, $hiddenpicture, false, $context);
        return true;
    }

    // Finish the game.
    $query = $DB->get_record_select( 'game_queries', "attemptid=$hiddenpicture->id AND mycol=0",
        null, 'id,glossaryentryid,attachment,questiontext');
    game_showpicture( $cm->id, $game, $attempt, $query, '', '', false);
    echo '<p><br/><span class="hiddenpicture_win">'.get_string( 'win', 'game').'</span></p>';
    global $CFG;

    echo '<br/>';

    echo "<a class=\"hiddenpicture_nextgame\" href=\"$CFG->wwwroot/mod/game/attempt.php?id={$cm->id}\">";
    echo get_string( 'nextgame', 'game').'</a>';

    echo "<a class=\"hiddenpicture_finish endgamebutton\" href=\"{$CFG->wwwroot}/course/view.php?id=$cm->course\">".get_string( 'finish', 'game').'</a> ';

    return false;
}

/**
 * Show picture
 *
 * @param int $id
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $query
 * @param object $cells
 * @param int $foundcells
 * @param boolean $usemap
 */
function game_showpicture( $id, $game, $attempt, $query, $cells, $foundcells, $usemap) {
    global $CFG;

    $filenamenumbers = str_replace( "\\", '/', new moodle_url('mod/game/hiddenpicture/numbers.png'));
    if ($usemap) {
        $cols = $game->param1;
        $rows = $game->param2;
    } else {
        $cols = $rows = 0;
    }
    $params = "id=$id&id2=$attempt->id&f=$foundcells&cols=$cols&rows=$rows&cells=$cells&p={$query->attachment}&n=$filenamenumbers";
    $imagesrc = "hiddenpicture/picture.php?$params";

    $fs = get_file_storage();
    $file = get_file_storage()->get_file_by_hash( $query->attachment);
    $image = $file->get_imageinfo();
    if ($game->param4 > 10) {
        $width = $game->param4;
        $height = $image[ 'height'] * $width / $image[ 'width'];
    } else if ( $game->param5 > 10) {
        $height = $game->param5;
        $width = $image[ 'width'] * $height / $image[ 'height'];
    } else {
        $width = $image[ 'width'];
        $height = $image[ 'height'];
    }

    echo "<IMG SRC=\"$imagesrc\" width=$width ";
    if ($usemap) {
        echo " USEMAP=\"#mapname\" ";
    }
    echo " BORDER=\"1\">";

    if ($usemap) {
        echo "<MAP NAME=\"mapname\">";
        $pos = 0;
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $pos++;
                $x1 = $col * $width / $cols;
                $y1 = $row * $height / $rows;
                $x2 = $x1 + $width / $cols;
                $y2 = $y1 + $height / $rows;
                $q = "a$pos";
                echo "<AREA SHAPE=\"rect\" COORDS=\"$x1,$y1,$x2,$y2\" HREF=\"#$q\" ALT=\"$pos\">";
            }
        }
        echo "</MAP>";
    }
}
/**
 * Check glossary entries
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param boolean $finishattempt
 * @param stdClass $course
 */
function game_hiddenpicture_check_glossaryentries( $cm, $game, $attempt, $hiddenpicture, $finishattempt, $course) {
    global $DB;

    $responses = data_submitted();

    // This function returns offsetentries, numbers, correctquestions.
    $offsetentries = game_hiddenpicture_compute_offsetquestions( $game->sourcemodule, $attempt, $numbers, $correctquestions);

    $entrieslist = game_hiddenpicture_getquestionlist( $offsetentries );

    // Load the glossary entries.
    if (!($entries = $DB->get_records_select( 'glossary_entries', "id IN ($entrieslist)"))) {
        print_error( get_string('noglossaryentriesfound', 'game'));
    }
    foreach ($entries as $entry) {
        $answerundefined = optional_param('resp'.$entry->id, 'undefined', PARAM_TEXT);
        if ($answerundefined == 'undefined') {
            continue;
        }
        $answer = optional_param('resp'.$entry->id, '', PARAM_TEXT);
        if ($answer == '') {
            continue;
        }
        if (game_upper( $entry->concept) != game_upper( $answer)) {
            continue;
        }
        // Correct answer.
        $select = "attemptid=$attempt->id";
        $select .= " AND glossaryentryid=$entry->id AND mycol>0";
        // Check the student guesses not source glossary entry.
        $select .= " AND questiontext is null";

        $query = new stdClass();
        if (($query->id = $DB->get_field_select( 'game_queries', 'id', $select)) == 0) {
            echo "not found $select<br>";
            continue;
        }

        game_update_queries( $game, $attempt, $query, 1, $answer);
    }

    game_hiddenpicture_check_last( $cm, $game, $attempt, $hiddenpicture, $finishattempt, $course);

    return true;
}
/**
 * This is the last function after submiting the answers.
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param boolean $finishattempt
 * @param stdClass $course
 */
function game_hiddenpicture_check_last( $cm, $game, $attempt, $hiddenpicture, $finishattempt, $course) {
    global $CFG, $DB;

    $correct = $DB->get_field_select( 'game_queries', 'COUNT(*) AS c', "attemptid=$attempt->id AND score > 0.9");
    $all = $DB->get_field_select( 'game_queries', 'COUNT(*) AS c', "attemptid=$attempt->id");

    if ($all) {
        $score = $correct / $all;
    } else {
        $score = 0;
    }
    game_updateattempts( $game, $attempt, $score, $finishattempt, $cm, $course);
}

/**
 * Checks questions
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $hiddenpicture
 * @param boolean $finishattempt
 * @param stdClass $course
 * @param stdClass $context
 */
function game_hiddenpicture_check_questions( $cm, $game, $attempt, $hiddenpicture, $finishattempt, $course, $context) {
    global $DB;

    $responses = data_submitted();

    $offsetquestions = game_hiddenpicture_compute_offsetquestions( $game->sourcemodule, $attempt, $numbers, $correctquestions);

    $questionlist = game_hiddenpicture_getquestionlist( $offsetquestions);

    $questions = game_hiddenpicture_getquestions( $questionlist);

    foreach ($questions as $question) {
        $query = new stdClass();

        $select = "attemptid=$attempt->id";
        $select .= " AND questionid=$question->id";
        if (($query->id = $DB->get_field_select( 'game_queries', 'id', $select)) == 0) {
            die( "problem game_hiddenpicture_check_questions (select=$select)");
            continue;
        }

        $grade = game_grade_responses( $question, $responses, 100, $answertext, $answered);
        if ($answered == false) {
            continue;
        }
        if ($grade < 99) {
            // Wrong answer.
            game_update_queries( $game, $attempt, $query, $grade / 100, $answertext);
            continue;
        }

        // Correct answer.
        game_update_queries( $game, $attempt, $query, 1, $answertext);
    }

    game_hiddenpicture_check_last( $cm, $game, $attempt, $hiddenpicture, $finishattempt, $course);
}
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
 * This file plays the game millionaire.
 *
 * @package    mod_game
 * @copyright  2007 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Plays the millionaire
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $millionaire
 * @param stdClass $context
 * @param stdClass $course
 */
function game_millionaire_continue( $cm, $game, $attempt, $millionaire, $context, $course) {
    // User must select quiz or question as a source module.
    if (($game->quizid == 0) and ($game->questioncategoryid == 0)) {
        if ($game->sourcemodule == 'quiz') {
            print_error( get_string( 'millionaire_must_select_quiz', 'game'));
        } else {
            print_error( get_string( 'millionaire_must_select_questioncategory', 'game'));
        }
    }

    if ($attempt != false and $millionaire != false) {
        // Continue an existing game.
        return game_millionaire_play( $cm, $game, $attempt, $millionaire, $context, $course);
    }

    if ($attempt == false) {
        $attempt = game_addattempt( $game);
    }

    $newrec = new stdClass();
    $newrec->id = $attempt->id;
    $newrec->queryid = 0;
    $newrec->level = 0;
    $newrec->state = 0;

    if (!game_insert_record(  'game_millionaire', $newrec)) {
        print_error( 'error inserting in game_millionaire');
    }

    game_millionaire_play( $cm, $game, $attempt, $newrec, $context, $course);
}

/**
 * Plays the millionaire
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $millionaire
 * @param stdClass $context
 * @param stdClass $course
 */
function game_millionaire_play( $cm, $game, $attempt, $millionaire, $context, $course) {
    global $DB;

    $buttons = optional_param('buttons', 0, PARAM_INT);
    $help5050x = optional_param('Help5050_x', 0, PARAM_INT);
    $helptelephonex = optional_param('HelpTelephone_x', 0, PARAM_INT);
    $helppeoplex = optional_param('HelpPeople_x', 0, PARAM_INT);
    $quitx = optional_param('Quit_x', 0, PARAM_INT);

    if ($millionaire->queryid) {
        $query = $DB->get_record( 'game_queries', array( 'id' => $millionaire->queryid));
    } else {
        $query = new StdClass;
    }

    $found = 0;
    for ($i = 1; $i <= $buttons; $i++) {
        $name = 'btAnswer'.$i;
        $answer = optional_param($name, '', PARAM_RAW);
        if (!empty($answer)) {
            game_millionaire_OnAnswer( $cm, $game, $attempt, $millionaire, $query, $i, $context, $course);
            $found = 1;
        }
    }

    if ($found == 1) {
        $found = $found; // Nothing.
    } else if (!empty($help5050x)) {
        game_millionaire_OnHelp5050( $game, $cm->id,  $millionaire, $game, $query, $context);
    } else if (!empty($helptelephonex)) {
        game_millionaire_OnHelpTelephone( $game, $cm->id, $millionaire, $query, $context);
    } else if (!empty($helppeoplex)) {
        game_millionaire_OnHelpPeople( $game, $cm->id, $millionaire, $query, $context);
    } else if (!empty($quitx)) {
        game_millionaire_OnQuit( $cm,  $game, $attempt, $query, $course);
    } else {
        game_millionaire_ShowNextQuestion( $cm, $game, $attempt, $millionaire, $context, $course);
    }
}

/**
 * Shows the grid
 *
 * @param stdClass $game
 * @param stdClass $millionaire
 * @param int $id
 * @param stdClass $query
 * @param array $aanswer
 * @param stdClass $info
 * @param stdClass $context
 */
function game_millionaire_showgrid( $game, $millionaire, $id, $query, $aanswer, $info, $context) {
    global $CFG, $OUTPUT;

    $question = str_replace( array("\'", '\"'), array("'", '"'), $query->questiontext);

    if ($game->param8 == '') {
        $color = 408080;
    } else {
        $color = substr( '000000'.base_convert($game->param8, 10, 16), -6);
    }

    $state = $millionaire->state;
    $level = $millionaire->level;

    echo '<form name="Form1" method="post" action="attempt.php" id="Form1">';
    echo html_writer::start_div('millionnaire-main');
    echo html_writer::start_div('millionnaire-header-wrapper');
    echo html_writer::start_div('millionnaire-header');

    $dirgif = 'millionaire/1/';
    if ($state & 1) {
        $gif = "5050x";
        $disabled = "disabled=1";
    } else {
        $gif = "5050";
        $disabled = "";
    }
    $src = game_pix_url($dirgif.$gif, 'mod_game');
    echo '<input type="image" '.$disabled.' name="Help5050" id="Help5050" title="50 50" src="'.$src.'" alt="" border="0">';

    if ($state & 2) {
        $gif = "telephonex";
        $disabled = "disabled=1";
    } else {
        $gif = "telephone";
        $disabled = "";
    }

    echo '<input type="image" name="HelpTelephone" '.$disabled.
        ' id="HelpTelephone" title="'.get_string( 'millionaire_telephone', 'game').
        '" src="'.game_pix_url($dirgif.$gif, 'mod_game').'" alt="">';

    if ($state & 4) {
        $gif = "peoplex";
        $disabled = "disabled=1";
    } else {
        $gif = "people";
        $disabled = "";
    }
    echo '<input type="image" name="HelpPeople" '.$disabled.' id="HelpPeople" title="'.
        get_string( 'millionaire_helppeople', 'game').'" src="'.
        game_pix_url($dirgif.$gif, 'mod_game').'" alt="">';

    echo '<input type="image" name="Quit" id="Quit" title="'.
        get_string( 'millionaire_quit', 'game').'" src="'.
        game_pix_url($dirgif.'x', 'mod_game').'" alt="" border="0">';
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('millionnaire-progress-wrapper');
    $aval = [100, 200, 300, 400, 500, 1000, 1500, 2000, 4000, 5000, 10000, 20000, 40000, 80000, 150000];
    for ($i = 15; $i >= 1; $i--) {
        $statusclass = (($i < $level + 1) ? ' done' : (($i == $level +1) ? ' current' : ''));
        echo html_writer::start_div('millionnaire-progress' . $statusclass);
        echo html_writer::div($i, 'milionnaire-progress-step');
        echo html_writer::div(sprintf("%10d", $aval[ $i - 1]), 'milionnaire-progress-point');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    echo html_writer::start_div('millionnaire-question-answers-wrapper');
    $question = game_show_query($game, $query, $question);
    echo html_writer::div($question, 'millionnaire-question');

    $bfirst = true;
    $letters = get_string('millionaire_lettersall', 'game');
    if (($letters == '') or ($letters == '-')) {
        $letters = get_string('lettersall', 'game');
    }

    echo html_writer::start_div('millionnaire-answers-wrapper');
    for ($i = 1; $i <= count( $aanswer); $i++) {
        $name = "btAnswer".$i;
        $s = game_substr( $letters, $i - 1, 1);
        $btnattr = ['type' => 'submit', 'name' => $name, 'value' => $s, 'id' => $name . '1'];
        if ($state == 15) {
            $btnattr['disabled'] = 'disabled';
        }
        $button =  html_writer::empty_tag('input', $btnattr);
        $text = game_filtertext($aanswer[ $i - 1], $game->course);
        $answer = html_writer::tag('label', $text, ['id' => $name, 'for' => $name.'1']);

        if ($aanswer[ $i - 1] != "") {
            echo html_writer::start_div('millionnaire-answer');
            echo html_writer::div($button, 'milionnaire-answer-btn');
            echo html_writer::div($answer, 'milionnaire-answer-text');
            echo html_writer::end_div();

        }
    }
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'state', 'value' => $state]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'buttons', 'value' => count($aanswer)]);
    echo html_writer::end_div();

    $info = game_filtertext($info, $game->course);
    if (!empty($info)) {
        echo html_writer::start_div('milionnaire-info-wrapper');
        echo html_writer::div($info, 'milionnaire-answer-info');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
}

/**
 * Show next question
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $millionaire
 * @param stdClass $context
 * @param stdClass $course
 */
function game_millionaire_shownextquestion( $cm, $game, $attempt, $millionaire, $context, $course) {
    game_millionaire_selectquestion( $aanswer, $game, $attempt, $millionaire, $query, $context, $cm, $course);

    if ($game->toptext != '') {
        echo html_writer::div(html_writer::span($game->toptext, 'milionnaire-toptext'), 'milionnaire-toptext-wrapper');
    }

    game_millionaire_showgrid( $game, $millionaire, $cm->id, $query, $aanswer, "", $context, $course);

    if ($game->bottomtext != '') {
        echo html_writer::div(html_writer::span($game->bottomtext, 'milionnaire-bottomtext'), 'milionnaire-bottomtext-wrapper');
    }
}

/**
 * Updates tables: games_millionaire, game_attempts, game_questions.
 *
 * @param array $aanswer
 * @param stdClass $game
 * @param stdClasss $attempt
 * @param stdClass $millionaire
 * @param stdClass $query
 * @param stdClass $context
 * @param stdClass $cm
 * @param stdClass $course
 */
function game_millionaire_selectquestion( &$aanswer, $game, $attempt, &$millionaire, &$query, $context, $cm, $course) {
    global $DB, $USER;

    if (($game->sourcemodule != 'quiz') and ($game->sourcemodule != 'question')) {
        print_error( get_string('millionaire_sourcemodule_must_quiz_question', 'game',
            get_string( 'modulename', 'quiz')).' '.get_string( 'modulename', $attempt->sourcemodule));
    }

    if ($millionaire->queryid != 0) {
        game_millionaire_loadquestions( $game, $millionaire, $query, $aanswer, $context);
        return;
    }

    if ($game->sourcemodule == 'quiz') {
        if ($game->quizid == 0) {
            print_error( get_string( 'must_select_quiz', 'game'));
        }
        if (game_get_moodle_version() < '02.06') {
            $select = "qtype='multichoice' AND quiz='$game->quizid' AND qmo.question=q.id".
                " AND qqi.question=q.id";
            $table = "{quiz_question_instances} qqi,{question} q, {question_multichoice} qmo";
            $order = '';
        } else if (game_get_moodle_version() < '02.07') {
            $select = "qtype='multichoice' AND quiz='$game->quizid' AND qmo.questionid=q.id".
                " AND qqi.question=q.id";
            $table = "{quiz_question_instances} qqi,{question} q, {qtype_multichoice_options} qmo";
            $order = '';
        } else {
            $select = "qtype='multichoice' AND qs.quizid='$game->quizid' AND qmo.questionid=q.id".
                " AND qs.questionid=q.id";
            $table = "{quiz_slots} qs,{question} q, {qtype_multichoice_options} qmo";
            $order = 'qs.page,qs.slot';
        }
    } else {
        // Source is questions.
        if ($game->questioncategoryid == 0) {
            print_error( get_string( 'must_select_questioncategory', 'game'));
        }

        // Include subcategories.
        $select = 'category='.$game->questioncategoryid;
        if ($game->subcategories) {
            $cats = question_categorylist( $game->questioncategoryid);
            if (count( $cats)) {
                $select = 'q.category in ('.implode(',', $cats).')';
            }
        }

        if (game_get_moodle_version() < '02.06') {
            $select .= " AND qtype='multichoice' AND qmo.single=1 AND qmo.question=q.id";
            $table = '{question} q, {question_multichoice} qmo';
        } else {
            $select .= " AND qtype='multichoice' AND qmo.single=1 AND qmo.questionid=q.id";
            $table = '{question} q, {qtype_multichoice_options} qmo';
        }
    }
    $select .= ' AND hidden=0';
    if ($game->shuffle or $game->quizid == 0) {
        $questionid = game_question_selectrandom( $game, $table, $select, 'q.id as id', true);
    } else {
        $questionid = game_millionaire_select_serial_question( $game, $table, $select, 'q.id as id', $millionaire->level, $order);
    }

    if ($questionid == 0) {
        print_error( get_string( 'no_questions', 'game'));
    }

    $q = $DB->get_record( 'question', array( 'id' => $questionid), 'id,questiontext');

    $recs = $DB->get_records( 'question_answers', array( 'question' => $questionid));

    if ($recs === false) {
        print_error( get_string( 'no_questions', 'game'));
    }

    $correct = 0;
    $ids = array();
    foreach ($recs as $rec) {
        $aanswer[] = game_filterquestion_answer(str_replace( '\"', '"', $rec->answer), $rec->id, $context->id, $game->course);

        $ids[] = $rec->id;
        if ($rec->fraction == 1) {
            $correct = $rec->id;
        }
    }

    $count = count( $aanswer);
    for ($i = 1; $i <= $count; $i++) {
        $sel = mt_rand(0, $count - 1);

        $temp = array_splice( $aanswer, $sel, 1);
        $aanswer[ ] = $temp[ 0];

        $temp = array_splice( $ids, $sel, 1);
        $ids[ ] = $temp[ 0];
    }

    $query = new StdClass;
    $query->attemptid = $attempt->id;
    $query->gamekind = $game->gamekind;
    $query->gameid = $game->id;
    $query->userid = $USER->id;
    $query->sourcemodule = $game->sourcemodule;
    $query->glossaryentryid = 0;
    $query->questionid = $questionid;
    $query->questiontext = $q->questiontext;
    $query->answertext = implode( ',', $ids);
    $query->correct = array_search( $correct, $ids) + 1;
    if (!$query->id = $DB->insert_record(  'game_queries', $query)) {
        print_error( 'error inserting to game_queries');
    }

    $updrec = new StdClass;
    $updrec->id = $millionaire->id;
    $updrec->queryid = $query->id;

    if (!$newid = $DB->update_record(  'game_millionaire', $updrec)) {
        print_error( 'error updating in game_millionaire');
    }

    $score = $millionaire->level / 15;
    game_update_queries( $game, $attempt, $query, $score, '');
}

/**
 * Select serial question
 *
 * @param stdClass $game
 * @param string $table
 * @param string $select
 * @param string $idfields
 * @param int $level
 * @param string $order
 */
function game_millionaire_select_serial_question( $game, $table, $select, $idfields = "id", $level, $order) {
    global $DB, $USER;

    $sql  = "SELECT $idfields,$idfields FROM ".$table." WHERE $select ";
    if ($order != '') {
        $sql .= " ORDER BY $order";
    }

    if (($recs = $DB->get_records_sql( $sql)) == false) {
        return false;
    }
    $questions = array();
    foreach ($recs as $rec) {
        $questions[] = $rec->id;
    }

    $count = count( $questions);
    if ($count == 0) {
        return false;
    }

    $from = round($level * ($count - 1) / 15);
    $to = round(max( $from, ($level + 1) * ($count - 1) / 15)) - 1;
    if ($to < $from) {
        $to = $from;
    }
    $pos = mt_rand( round( $from), round( $to));
    return $questions[ $pos];
}

/**
 * Load questions for millionaire
 *
 * @param stdClass $game
 * @param stdClass $millionaire
 * @param string $query
 * @param array $aanswer
 * @param stdClass $context
 */
function game_millionaire_loadquestions( $game, $millionaire, &$query, &$aanswer, $context) {
    global $DB;

    $query = $DB->get_record( 'game_queries', array( 'id' => $millionaire->queryid),
        'id,questiontext,answertext,correct,questionid');

    $aids = explode( ',', $query->answertext);
    $aanswer = array();
    foreach ($aids as $id) {
        $rec = $DB->get_record( 'question_answers', array( 'id' => $id), 'id,answer');

        $aanswer[] = game_filterquestion_answer(str_replace( '\"', '"', $rec->answer), $id, $context->id, $game->course);
    }
}

/**
 * Set state. Flag 1 is 5050, 2 is telephone 4 is people.
 *
 * @param stdClass $millionaire
 * @param string $mask
 */
function game_millionaire_setstate( &$millionaire, $mask) {
    global $DB;

    $millionaire->state |= $mask;

    $updrec = new stdClass();
    $updrec->id = $millionaire->id;
    $updrec->state = $millionaire->state;
    if (!$DB->update_record(  'game_millionaire', $updrec)) {
        print_error( 'error updating in game_millionaire');
    }
}

/**
 * One help 50-50
 *
 * @param stdClass $game
 * @param int $id
 * @param stdClass $millionaire
 * @param string $query
 * @param stdClass $context
 */
function game_millionaire_onhelp5050( $game, $id,  &$millionaire, $query, $context) {
    game_millionaire_loadquestions( $game, $millionaire, $query, $aanswer, $context);

    if (($millionaire->state & 1) != 0) {
        game_millionaire_showgrid( $game, $millionaire, $id, $query, $aanswer, '', $context);
        return;
    }

    game_millionaire_setstate( $millionaire, 1);

    $n = count( $aanswer);
    if ($n > 2) {
        for (;;) {
            $wrong = mt_rand( 1, $n);
            if ($wrong != $query->correct) {
                    break;
                }
            }
            for ($i = 1; $i <= $n; $i++) {
                if ($i <> $wrong and $i <> $query->correct) {
                    $aanswer[ $i - 1] = "";
                }
            }
        }

        game_millionaire_showgrid(  $game, $millionaire, $id, $query, $aanswer, '', $context);
    }

    /**
     * One help telephone
     *
     * @param stdClass $game
     * @param int $id
     * @param stdClass $millionaire
     * @param stdClass $query
     * @param stdClass $context
     */
    function game_millionaire_onhelptelephone(  $game, $id,  &$millionaire, $query, $context) {
        game_millionaire_loadquestions( $game, $millionaire, $query, $aanswer, $context);

        if (($millionaire->state & 2) != 0) {
            game_millionaire_ShowGrid( $game, $millionaire, $id, $query, $aanswer, '', $context);
            return;
        }

        game_millionaire_setstate( $millionaire, 2);

        $n = count( $aanswer);
        if ($n < 2) {
            $wrong = $query->correct;
        } else {
            for (;;) {
                $wrong = mt_rand( 1, $n);
                if ($wrong != $query->correct) {
                    break;
                }
            }
        }

        // With 80% gives the correct answer.
        if (mt_rand( 1, 10) <= 8) {
            $response = $query->correct;
        } else {
            $response = $wrong;
        }

        $info = get_string( 'millionaire_info_telephone', 'game').'<br><b>'.$aanswer[ $response - 1].'</b>';

    game_millionaire_showgrid( $game, $millionaire, $id, $query, $aanswer, $info, $context);
}

/**
 * One help people
 *
 * @param stdClass $game
 * @param int $id
 * @param stdClass $millionaire
 * @param stdClass $query
 * @param stdClass $context
 */
function game_millionaire_onhelppeople( $game, $id,  &$millionaire, $query, $context) {
    game_millionaire_loadquestions( $game, $millionaire, $query, $aanswer, $context);

    if (($millionaire->state & 4) != 0) {
        game_millionaire_showgrid( $game, $millionaire, $id, $query, $aanswer, '', $context);
        return;
    }

    game_millionaire_setstate( $millionaire, 4);

    $n = count( $aanswer);
    $sum = 0;
    $apercent = array();
    for ($i = 0; $i + 1 < $n; $i++) {
        $percent = mt_rand( 0, 100 - $sum);
        $apercent[ $i] = $percent;
        $sum += $percent;
    }
    $apercent[ $n - 1] = 100 - $sum;
    if (mt_rand( 1, 100) <= 80) {
        // With percent 80% sets in the correct answer the biggest percent.
        $maxpos = 0;
        for ($i = 1; $i + 1 < $n; $i++) {
            if ($apercent[ $i] >= $apercent[ $maxpos]) {
                $maxpos = $i;
            }
            $temp = $apercent[ $maxpos];
            $apercent[ $maxpos] = $apercent[ $query->correct - 1];
            $apercent[ $query->correct - 1] = $temp;
        }
    }

    $info = '<br>'.get_string( 'millionaire_info_people', 'game').':<br>';
    for ($i = 0; $i < $n; $i++) {
        $info .= "<br>".  game_substr( get_string( 'lettersall', 'game'), $i, 1) ." : ".$apercent[ $i]. ' %';
    }

    game_millionaire_showgrid( $game, $millionaire, $id, $query, $aanswer, game_substr( $info, 4), $context);
}

/**
 * Millionaire on answer
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $millionaire
 * @param stdClass $query
 * @param string $answer
 * @param stdClass $context
 * @param stdClass $course
 */
function game_millionaire_onanswer( $cm, $game, $attempt, &$millionaire, $query, $answer, $context, $course) {
    global $DB;

    game_millionaire_loadquestions( $game, $millionaire, $query, $aanswer, $context);
    if ($answer == $query->correct) {
        if ($millionaire->level < 15) {
            $millionaire->level++;
        }
        $finish = ($millionaire->level == 15 ? 1 : 0);
        $scorequestion = 1;
    } else {
        $finish = 1;
        $scorequestion = 0;
    }

    $score = $millionaire->level / 15;

    game_update_queries( $game, $attempt, $query, $scorequestion, $answer);
    game_updateattempts( $game, $attempt, $score, $finish, $cm, $course);

    $updrec = new stdClass();
    $updrec->id = $millionaire->id;
    $updrec->level = $millionaire->level;
    $updrec->queryid = 0;
    if (!$DB->update_record(  'game_millionaire', $updrec)) {
        print_error( 'error updating in game_millionaire');
    }

    if ($answer == $query->correct) {
        // Correct.
        if ($finish) {
            echo html_writer::div(get_string( 'win', 'game'), 'milionnaire-feedback-win');
            game_millionaire_OnQuit( $cm, $game, $attempt, $query, $course);
        } else {
            $millionaire->queryid = 0;  // So the next function select a new question.
        }
        game_millionaire_ShowNextQuestion( $cm, $game, $attempt, $millionaire, $context, $course);
    } else {
        // Wrong answer.
        $info = html_writer::start_div('millionnaire-feedback-wrong-wrapper').
        html_writer::div(get_string('millionaire_info_wrong_answer', 'game'), 'millionaire-info-wrong-answer-title').
        html_writer::div($aanswer[ $query->correct - 1], 'milionnaire-info-wrong-answer-answer');

        $millionaire->state = 15;
        game_millionaire_ShowGrid( $game, $millionaire, $cm->id, $query, $aanswer, $info, $context);
    }
}

/**
 * Millionaire on quit
 *
 * @param stdClass $cm
 * @param stdClass $game
 * @param stdClass $attempt
 * @param stdClass $query
 * @param stdClass $course
 */
function game_millionaire_onquit( $cm, $game, $attempt, $query, $course) {
    global $CFG, $DB;

    game_updateattempts( $game, $attempt, -1, true, $cm, $course);

    echo "<a href=\"{$CFG->wwwroot}/mod/game/attempt.php?id={$cm->id}\">".
        get_string( 'nextgame', 'game').'</a>     ';
    echo "<a class='endgamebutton' href=\"{$CFG->wwwroot}/course/view.php?id=$cm->course\">".get_string( 'finish', 'game').'</a> ';
}

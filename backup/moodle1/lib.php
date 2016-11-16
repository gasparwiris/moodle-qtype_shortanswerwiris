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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/type/wq/quizzes/quizzes.php');
require_once($CFG->dirroot . '/question/type/wq/lib.php');

class moodle1_qtype_shortanswerwiris_handler extends moodle1_qtype_shortanswer_handler{
    /**
     * @return array
     */
    public function get_question_subpaths() {
        return array(
            'ANSWERS/ANSWER',
            'SHORTANSWER',
            'SHORTANSWERWIRIS',
            'SHORTANSWERWIRIS/WIRISOPTIONS',
        );
    }

    /**
     * Appends the shortanswerwiris specific information to the question
     */
    public function process_question(array $data, array $raw) {
        $iscompound = false;
        $originalanswertext = '';

        if (isset($data['shortanswerwiris'][0]['wiriseditor'])) {
            $wiriseditor = array();
            parse_str($data['shortanswerwiris'][0]['wiriseditor'], $wiriseditor);
            if (isset($wiriseditor['multipleAnswers']) && $wiriseditor['multipleAnswers'] == true) {
                $iscompound = true;
            }
        }

        if ($iscompound) {
            foreach ($data['answers']['answer'] as $key => $value) {
                $originalanswertext = $value['answer_text'];
                $answertext = $this->wrsqz_convert_for_compound($originalanswertext);
                $data['answers']['answer'][$key]['answer_text'] = $answertext;
            }
        }

        parent::process_question($data, $raw);

        $data['actualshortanswerwiris']['id'] = $this->converter->get_nextid();

        $wirisprogram = '<question><wirisCasSession>';
        $wirisprogram .= htmlspecialchars(wrsqz_mathml_decode($data['shortanswerwiris'][0]['wirisprogram']), ENT_COMPAT, "utf-8");
        $wirisprogram .= '</wirisCasSession>';

        $wirisprogram .= '<correctAnswers>';
        foreach ($data['answers'] as $key => $value) {
            $answertext = $value[0]['answer_text'];
            $wirisprogram .= '<correctAnswer type="mathml">';
            if ($iscompound) {
                $wirisprogram .= htmlspecialchars($answertext, ENT_COMPAT, "utf-8");
            } else {
                $wirisprogram .= trim($answertext);
            }
            $wirisprogram .= '</correctAnswer>';
        }
        $wirisprogram .= '</correctAnswers>';

        if (isset($data['shortanswerwiris'][0]['wiriseditor'])) {
            $wirisprogram .= $this->wrsqz_get_extra_parameters($data, $iscompound, $originalanswertext);
        }
        $wirisprogram .= '</question>';
        $data['actualshortanswerwiris']['xml'] = $wirisprogram;
        $this->write_xml('question_xml', $data['actualshortanswerwiris'], array('/question_xml/id'));
    }

    protected function wrsqz_get_cas_for_computations($data) {
        $wrap = com_wiris_system_CallWrapper::getInstance();

        $wirisquestion = '';
        if (isset($data['shortanswerwiris'][0]['wirisoptions'][0]['wiriscasforcomputations'])) {
            if ($data['shortanswerwiris'][0]['wirisoptions'][0]['wiriscasforcomputations'] == 1) {
                $wrap->start();
                // @codingStandardsIgnoreStart
                $wirisquestion .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_SHOW_CAS . '">';
                $wirisquestion .= com_wiris_quizzes_impl_LocalData::$VALUE_SHOW_CAS_ADD;
                // @codingStandardsIgnoreEnd
                $wrap->stop();
                $wirisquestion .= '</data>';
            } else if ($data['shortanswerwiris'][0]['wirisoptions'][0]['wiriscasforcomputations'] == 2) {
                $wrap->start();
                // @codingStandardsIgnoreStart
                $wirisquestion .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_SHOW_CAS . '">';
                $wirisquestion .= com_wiris_quizzes_impl_LocalData::$VALUE_SHOW_CAS_REPLACE_INPUT;
                // @codingStandardsIgnoreEnd
                $wrap->stop();
                $wirisquestion .= '</data>';
            }
        } else {
            $wrap->start();
            // @codingStandardsIgnoreStart
            $wirisquestion .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_SHOW_CAS . '">';
            $wirisquestion .= com_wiris_quizzes_impl_LocalData::$VALUE_SHOW_CAS_FALSE;
            // @codingStandardsIgnoreEnd
            $wrap->stop();
            $wirisquestion .= '</data>';
        }
        return $wirisquestion;
    }

    protected function wrsqz_hidden_initial_cas_value($data) {
        $wrap = com_wiris_system_CallWrapper::getInstance();

        $wirisquestion = '';
        if (isset($data['shortanswerwiris'][0]['wirisoptions'][0]['hiddeninitialcasvalue'])) {
            $wrap->start();
            // @codingStandardsIgnoreLine
            $wirisquestion .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_CAS_INITIAL_SESSION . '">';
            $wrap->stop();
            $initialcasvalue = $data['shortanswerwiris'][0]['wirisoptions'][0]['hiddeninitialcasvalue'];
            $wirisquestion .= htmlspecialchars(wrsqz_mathml_decode(trim($initialcasvalue)), ENT_COMPAT, "UTF-8");
            $wirisquestion .= '</data>';
        }

        return $wirisquestion;
    }

    protected function wrsqz_get_extra_parameters($data, $iscompound, $originalanswertext) {
        $wrap = com_wiris_system_CallWrapper::getInstance();

        // Grade function.
        $wiriseditor = array();
        parse_str($data['shortanswerwiris'][0]['wiriseditor'], $wiriseditor);

        $wirisprogram = '';

        if (count($wiriseditor) > 0) {
            $wirisprogram .= '<assertions>';
            if (isset($wiriseditor['testFunctionName'])) {
                foreach ($data['answers']['answer'] as $key => $value) {
                    $wirisprogram .= '<assertion name="syntax_expression" correctAnswer="' . $key . '"/>';
                }
                foreach ($wiriseditor['testFunctionName'] as $key => $value) {
                    $wirisprogram .= '<assertion name="equivalent_function" correctAnswer="' . $key . '">';
                    $wirisprogram .= '<param name="name">' . $value . '</param>';
                    $wirisprogram .= '</assertion>';
                }
            } else {
                foreach ($data['answers']['answer'] as $key => $value) {
                    $wirisprogram .= '<assertion name="syntax_expression" correctAnswer="' . $key . '"/>';
                    $wirisprogram .= '<assertion name="equivalent_symbolic" correctAnswer="' . $key . '"/>';
                }
            }
            $wirisprogram .= '</assertions>';

            // Editor and compound answer.
            $wirisprogram .= '<localData>';
            if (!$iscompound) {
                if (isset($wiriseditor['editor']) && $wiriseditor['editor'] == true) {
                    $wrap->start();
                    // @codingStandardsIgnoreStart
                    $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_INPUT_FIELD . '">';
                    $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_INPUT_FIELD_INLINE_EDITOR;
                    // @codingStandardsIgnoreEnd
                    $wrap->stop();
                    $wirisprogram .= '</data>';
                } else {
                    $wrap->start();
                    // @codingStandardsIgnoreStart
                    $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_INPUT_FIELD . '">';
                    $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_INPUT_FIELD_PLAIN_TEXT;
                    // @codingStandardsIgnoreEnd
                    $wrap->stop();
                    $wirisprogram .= '</data>';
                }
            } else {
                $wrap->start();
                // @codingStandardsIgnoreStart
                $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_INPUT_FIELD . '">';
                $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_INPUT_FIELD_POPUP_EDITOR;
                // @codingStandardsIgnoreEnd
                $wirisprogram .= '</data>';

                // @codingStandardsIgnoreStart
                $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_COMPOUND_ANSWER_GRADE . '">';
                $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_COMPOUND_ANSWER_GRADE_DISTRIBUTE;
                // @codingStandardsIgnoreEnd
                $wirisprogram .= '</data>';

                $distribution = $this->wrsqz_get_distribution($originalanswertext);
                // @codingStandardsIgnoreLine
                $keyopenaswer = com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_COMPOUND_ANSWER_GRADE_DISTRIBUTION;
                $wirisprogram .= '<data name="' . $keyopenaswer. '">';
                if ($distribution != '') {
                    $wirisprogram .= $distribution;
                }
                $wirisprogram .= '</data>';
                $wrap->stop();
            }
            if (isset($wiriseditor['multipleAnswers']) && $wiriseditor['multipleAnswers'] == true) {
                $wrap->start();
                // @codingStandardsIgnoreStart
                $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_COMPOUND_ANSWER . '">';
                $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_COMPOUND_ANSWER_TRUE;
                // @codingStandardsIgnoreEnd
                $wrap->stop();
                $wirisprogram .= '</data>';
            } else {
                $wrap->start();
                // @codingStandardsIgnoreStart
                $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_COMPOUND_ANSWER . '">';
                $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_COMPOUND_ANSWER_FALSE;
                // @codingStandardsIgnoreEnd
                $wrap->stop();
                $wirisprogram .= '</data>';
            }

            if (isset($data['shortanswerwiris'][0]['wirisoptions']) && count($data['shortanswerwiris'][0]['wirisoptions']) > 0) {
                $wirisprogram .= $this->wrsqz_get_cas_for_computations($data);
                $wirisprogram .= $this->wrsqz_hidden_initial_cas_value($data);
            }

            $wirisprogram .= '</localData>';

        } else {
            $wirisprogram .= '<localData>';
            if (isset($data['shortanswerwiris'][0]['wirisoptions']) && count($data['shortanswerwiris'][0]['wirisoptions']) > 0) {
                $wirisprogram .= $this->wrsqz_get_cas_for_computations($data);
                $wirisprogram .= $this->wrsqz_hidden_initial_cas_value($data);
            }
            $wrap->start();
            // @codingStandardsIgnoreStart
            $wirisprogram .= '<data name="' . com_wiris_quizzes_impl_LocalData::$KEY_OPENANSWER_INPUT_FIELD . '">';
            $wirisprogram .= com_wiris_quizzes_impl_LocalData::$VALUE_OPENANSWER_INPUT_FIELD_PLAIN_TEXT;
            // @codingStandardsIgnoreEnd
            $wirisprogram .= '</data>';
            $wrap->stop();
            $wirisprogram .= '</localData>';
        }

        return $wirisprogram;
    }

    private function wrsqz_convert_for_compound($text) {
        $answerarray = array();
        $compoundanswertext = '<math xmlns="http://www.w3.org/1998/Math/MathML">';

        $text = trim($text);
        if (!strpos($text, '(')) {
            $answerarray = explode(" ", $text);
            foreach ($answerarray as $key => $value) {
                if ($key != 0) {
                    $compoundanswertext .= '<mspace linebreak="newline"/>';
                }
                $value = trim($value);
                $compoundanswertext .= '<mi>' . substr($value, 1) . '</mi><mo>=</mo><mi>' . $value . '</mi>';
            }
        } else {
            $answerarray = explode(")", $text);
            foreach ($answerarray as $key => $value) {
                if ($value != '') {
                    if ($key != 0) {
                        $compoundanswertext .= '<mspace linebreak="newline"/>';
                    }
                    $openpar = strpos($value, '(');
                    $value = trim(substr($value, 0, $openpar));
                    $compoundanswertext .= '<mi>' . substr($value, 1) . '</mi><mo>=</mo><mi>' . $value . '</mi>';
                }
            }
        }

        $compoundanswertext .= '</math>';
        return $compoundanswertext;
    }


    private function wrsqz_get_distribution($text) {
        $distribution = '';
        $text = trim($text);
        $answerarray = explode("#", $text);

        foreach ($answerarray as $key => $value) {
            if (strpos($value, '(')) {
                $value = trim($value);
                $compoundarray = explode(" ", $value);
                $distribution .= $compoundarray[1] . ' ';
            }
        }
        $distribution = str_replace('(', '', $distribution);
        $distribution = str_replace(')', '', $distribution);
        return trim($distribution);
    }
}

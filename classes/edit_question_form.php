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
 * @authors Mike Churchward & Joseph Rézeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionnaire
 */
require_once($CFG->libdir . '/formslib.php');

class mod_questionnaire_edit_question_form extends moodleform {

    public function definition() {
        global $questionnaire, $question, $SESSION;

        // The 'sticky' required response value for further new questions.
        if (isset($SESSION->questionnaire->required) && !isset($question->qid)) {
            $question->required = $SESSION->questionnaire->required;
        }
        if (!isset($question->type_id)) {
            print_error('undefinedquestiontype', 'questionnaire');
        }

        $mform =& $this->_form;

        // Each question can provide its own form elements to the provided form, or use the default ones.
        //Splitting the formcreation into two parts, to fit the repeated area for advdependencies in between
        if (!$question->edit_form_pre_dependencies($mform, $questionnaire, $this->_customdata['modcontext'])) {
            print_error("Question type had an unknown error in the edit_form method.");
        }
        
        //Create a new area for multiple dependencies
        //FIXME Has(?) to be here, because it requires moodleform. Would be more consistent to place it in base.php
        //Checking for $questionnaire->navigate == 1 for the original branching is still in base.php
        if ($questionnaire->navigate == 2) {
        	$position = ($question->position !== 0) ? $question->position : count($questionnaire->questions) + 1;
        	$dependencies = questionnaire_get_dependencies($questionnaire->questions, $position);
        	$advchildren = [];
        	if (isset($question->qid)) {
        		//TODO this should be placed in locallib, see questionnaire_get_descendants
        		//Use also for the delete dialoque later
        		foreach ($questionnaire->questions as $questionlistitem) {
        			if (isset($questionlistitem->advdependencies)) {
        				foreach ($questionlistitem->advdependencies as $outeradvdependencies) {
        					if ($outeradvdependencies->adv_dependquestion == $question->qid) {
        						$advchildren[] = $outeradvdependencies;
        					}
        				}
        			}
        		}
        	}
        	
        	if (count($dependencies) > 1) {
        		//TODO Replace static strings and set language variables
        		$mform->addElement('header', 'advdependencies_hdr', 'Dependencies');
        		$mform->setExpanded('advdependencies_hdr');

        		$advdependenciescount = count($question->advdependencies);
        		
        		echo '<pre>'; print_r($question->advdependencies); echo '</pre>';
        		
        		//No childs, so we can add and change dependencies
        		if (count($advchildren) == 0) {
        			//TODO Replace static strings and set language variables
        			$select = $mform->createElement('select', 'advdependlogic', 'Condition', array('This answer not given', 'This answer given'));
        			$select->setSelected('1');
        			$groupitems = array();
        			$groupitems[] =& $mform->createElement('selectgroups', 'advdependquestions', 'Parent', $dependencies);
        			$groupitems[] =& $select;
        			$group = $mform->createElement('group', 'selectdependencies', get_string('dependquestion', 'questionnaire'), $groupitems, ' ', false);
        			$this->repeat_elements(array($group), $advdependenciescount + 1, array(), 'numdependencies', 'adddependencies',2);
       			} else {
       			// Has childs, now we have to check, whether to show a message or the list of fixed dependencies
      				if ($advdependenciescount == 0){
      					$mform->addElement('static', 'selectdependency'.$i, get_string('dependquestion', 'questionnaire'),
      							'<div class="dimmed_text">Dependencies can not be changed, because the flow for other questions allready depends on the existing behaviour.</div>');
      				} else {
      					//FIXME this is a fast workaround, a proper implementation should be in locallib
      					foreach ($question->advdependencies as $advdependencyhelper) {
      						$advdependencyhelper->dependquestion = $advdependencyhelper->adv_dependquestion;
      						$advdependencyhelper->dependchoice = $advdependencyhelper->adv_dependchoice;
      						$advdependencyhelper->position = 0;
      						$advdependencyhelper->name = null;
      						$advdependencyhelper->content = null;
      						$advdependencyhelper->id = 0;
      						
      						$parent = questionnaire_get_parent ($advdependencyhelper);
      						$fixeddependencies[] = $parent [0]['parent'];
      					}
      					
      					$mform->addElement('static', 'selectdependency', null,
      							'<div class="dimmed_text">Dependencies can not be changed, because the flow for other questions allready depends on the existing behaviour.</div>');
						for ($i=0;$i<count($fixeddependencies);$i++) {

							$mform->addElement('static', 'selectdependency_'.$i, get_string('dependquestion', 'questionnaire'),
									'<div class="dimmed_text">'.$fixeddependencies[$i].'</div>');
						}
      				}
 				}
        		//TODO Replace static strings and set language variables
        		$mform->addElement('header', 'qst_and_choices_hdr', 'Questiontext and answers');
        	}
        }
        
        if (!$question->edit_form_post_dependencies($mform, $questionnaire, $this->_customdata['modcontext'])) {
        	print_error("Question type had an unknown error in the edit_form method.");
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // If this is a rate question.
        if ($data['type_id'] == QUESRATE) {
            if ($data['length'] < 2) {
                $errors["length"] = get_string('notenoughscaleitems', 'questionnaire');
            }
            // If this is a rate question with no duplicates option.
            if ($data['precise'] == 2 ) {
                $allchoices = $data['allchoices'];
                $allchoices = explode("\n", $allchoices);
                $nbvalues = 0;
                foreach ($allchoices as $choice) {
                    if ($choice && !preg_match("/^[0-9]{1,3}=/", $choice)) {
                            $nbvalues++;
                    }
                }
                if ($nbvalues < 2) {
                    $errors["allchoices"] = get_string('noduplicateschoiceserror', 'questionnaire');
                }
            }
        }

        return $errors;
    }
}

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

namespace mod_booking\booking_rules\rules;

use context_system;
use mod_booking\booking_rules\booking_rule;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Rule to send a mail notification based on an event and additional settings.
 *
 * @package mod_booking
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_sendmail_cpf implements booking_rule {

    /** @var string $rulename */
    public $rulename = null;

    /** @var string $event */
    public $event = null;

    /** @var string $field */
    public $field = null;

    /** @var string $operator */
    public $operator = null;

    /** @var string $value */
    public $value = null;

    /** @var string $template */
    public $template = null;

    /**
     * Load json data form DB into the object.
     * @param stdClass $record a rule record from DB
     */
    public function set_ruledata(stdClass $record) {
        $this->rulename = $record->rulename;
        $ruleobj = json_decode($record->rulejson);
        $this->event = $ruleobj->event;
        $this->field = $ruleobj->field;
        $this->operator = $ruleobj->operator;
        $this->value = $ruleobj->value;
        $this->template = $ruleobj->template;
    }

    /**
     * Load data directly from JSON.
     * @param string $json a json string for a booking rule
     */
    public function set_ruledata_from_json(string $json) {
        // TODO.
    }

    /**
     * Only customizable functions need to return their necessary form elements.
     *
     * @param MoodleQuickForm $mform
     * @param int $optionid
     * @return void
     */
    public function add_rule_to_mform(MoodleQuickForm &$mform,
        array &$repeatedrules, array &$repeateloptions) {
        global $DB;

        // Get a list of all booking events.
        $bookingevents = get_list_of_booking_events();

        // Workaround: We need a group to get hideif to work.
        $groupitems = [];
        $groupitems[] = $mform->createElement('static', 'rule_sendmail_cpf_desc', '',
            get_string('rule_sendmail_cpf_desc', 'mod_booking'));
        $repeatedrules[] = $mform->createElement('group', 'rule_sendmail_cpf_desc_group', '',
            $groupitems, null, false);
        $repeateloptions['rule_sendmail_cpf_desc_group']['hideif'] = array('bookingrule', 'neq', 'rule_sendmail_cpf');

        // Event which should trigger the rule.
        $repeatedrules[] = $mform->createElement('select', 'rule_sendmail_cpf_event',
            get_string('rule_event', 'mod_booking'), $bookingevents);
        $repeateloptions['rule_sendmail_cpf_event']['type'] = PARAM_TEXT;
        $repeateloptions['rule_sendmail_cpf_event']['disabledif'] = array('bookingrule', 'eq', 'rule_sendmail_cpf');
        $repeateloptions['rule_sendmail_cpf_event']['hideif'] = array('bookingrule', 'neq', 'rule_sendmail_cpf');

        // Custom user profile field to be checked.
        $customuserprofilefields = $DB->get_records('user_info_field', null, '', 'id, name, shortname');
        if (!empty($customuserprofilefields)) {
            $customuserprofilefieldsarray = [];
            $customuserprofilefieldsarray[0] = get_string('userinfofieldoff', 'mod_booking');

            // Create an array of key => value pairs for the dropdown.
            foreach ($customuserprofilefields as $customuserprofilefield) {
                $customuserprofilefieldsarray[$customuserprofilefield->shortname] = $customuserprofilefield->name;
            }

            $repeatedrules[] = $mform->createElement('select', 'rule_sendmail_cpf_field',
                get_string('rule_customprofilefield', 'mod_booking'), $customuserprofilefieldsarray);
            $repeateloptions['rule_sendmail_cpf_field']['disabledif'] = array('bookingrule', 'eq', 'rule_sendmail_cpf');
            $repeateloptions['rule_sendmail_cpf_field']['hideif'] =
                array('bookingrule', 'neq', 'rule_sendmail_cpf');

            $operators = [
                '=' => get_string('equals', 'mod_booking'),
                '~' => get_string('contains', 'mod_booking')
            ];
            $repeatedrules[] = $mform->createElement('select', 'rule_sendmail_cpf_operator',
                get_string('rule_operator', 'mod_booking'), $operators);
            $repeateloptions['rule_sendmail_cpf_operator']['disabledif'] = array('bookingrule', 'eq', 'rule_sendmail_cpf');
            $repeateloptions['rule_sendmail_cpf_operator']['hideif'] =
                array('bookingrule', 'neq', 'rule_sendmail_cpf');

            $repeatedrules[] = $mform->createElement('text', 'rule_sendmail_cpf_value',
                get_string('rule_value', 'mod_booking'));
                $repeateloptions['rule_sendmail_cpf_value']['type'] = PARAM_TEXT;
            $repeateloptions['rule_sendmail_cpf_value']['disabledif'] = array('bookingrule', 'eq', 'rule_sendmail_cpf');
            $repeateloptions['rule_sendmail_cpf_value']['hideif'] =
                array('bookingrule', 'neq', 'rule_sendmail_cpf');
        }

        // Mail template. We need to use text area as editor does not work correctly.
        $repeatedrules[] = $mform->createElement('textarea', 'rule_sendmail_cpf_template',
            get_string('rule_mailtemplate', 'mod_booking'), 'wrap="virtual" rows="20" cols="25"');
        $repeateloptions['rule_sendmail_cpf_template']['disabledif'] = array('bookingrule', 'eq', 'rule_sendmail_cpf');
        $repeateloptions['rule_sendmail_cpf_template']['hideif'] = array('bookingrule', 'neq', 'rule_sendmail_cpf');

    }

    /**
     * Get the name of the rule.
     * @return string the name of the rule
     */
    public function get_name_of_rule() {
        return get_string('rule_sendmail_cpf', 'mod_booking');
    }

    /**
     * Save the JSON for all sendmail_cpf rules defined in form.
     * @param stdClass &$data form data reference
     */
    public static function save_rules(stdClass &$data) {
        global $DB;
        foreach ($data->bookingrule as $idx => $rulename) {
            if ($rulename == 'rule_sendmail_cpf') {
                $ruleobj = new stdClass;
                $ruleobj->rulename = $data->bookingrule[$idx];
                $ruleobj->event = $data->rule_sendmail_cpf_event[$idx];
                $ruleobj->field = $data->rule_sendmail_cpf_field[$idx];
                $ruleobj->operator = $data->rule_sendmail_cpf_operator[$idx];
                $ruleobj->value = $data->rule_sendmail_cpf_value[$idx];
                $ruleobj->template = $data->rule_sendmail_cpf_template[$idx];

                $record = new stdClass;
                $record->rulename = $data->bookingrule[$idx];
                $record->rulejson = json_encode($ruleobj);

                $DB->insert_record('booking_rules', $record);
            }
        }
    }

    /**
     * Sets the rule defaults when loading the form.
     * @param stdClass &$data reference to the default values
     * @param stdClass $record a record from booking_rules
     */
    public function set_defaults(stdClass &$data, stdClass $record) {
        $idx = $record->id - 1;
        $data->bookingrule[$idx] = $record->rulename;
        $ruleobj = json_decode($record->rulejson);
        $data->rule_sendmail_cpf_event[$idx] = $ruleobj->event;
        $data->rule_sendmail_cpf_field[$idx] = $ruleobj->field;
        $data->rule_sendmail_cpf_operator[$idx] = $ruleobj->operator;
        $data->rule_sendmail_cpf_value[$idx] = $ruleobj->value;
        $data->rule_sendmail_cpf_template[$idx] = $ruleobj->template;
    }

    /**
     * Execute the rule.
     * @param int $optionid optional
     * @param int $userid optional
     */
    public function execute(int $optionid = null, int $userid = null) {
        // TODO.
        return;
    }

    /**
     * This function is called on execution of adhoc tasks,
     * so we can see if the rule still applies and the adhoc task
     * shall really be executed.
     *
     * @param int $optionid
     * @param int $userid
     * @param int $nextruntime
     * @return bool true if the rule still applies, false if not
     */
    public function check_if_rule_still_applies(int $optionid, int $userid, int $nextruntime): bool {
        return false; // TODO.
    }
}
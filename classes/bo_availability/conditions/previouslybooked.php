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
 * Base class for a single booking option availability condition.
 *
 * All bo condition types must extend this class.
 *
 * @package mod_booking
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace mod_booking\bo_availability\conditions;

use mod_booking\bo_availability\bo_condition;
use mod_booking\bo_availability\bo_info;
use mod_booking\booking_option_settings;
use mod_booking\singleton_service;
use mod_booking\utils\wb_payment;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * This class takes the configuration from json in the available column of booking_options table.
 *
 * All bo condition types must extend this class.
 *
 * @package mod_booking
 * @copyright 2022 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class previouslybooked implements bo_condition {

    /** @var int $id Id is set via json during construction */
    public $id = null;

    /** @var stdClass $customsettings an stdclass coming from the json which passes custom settings */
    public $customsettings = null;

    /**
     * Constructor.
     *
     * @param integer $id
     * @return void
     */
    public function __construct(int $id = null) {

        if ($id) {
            $this->id = $id;
        }
    }

    /**
     * Needed to see if class can take JSON.
     * @return bool
     */
    public function is_json_compatible(): bool {
        return true; // Customizable condition.
    }

    /**
     * Needed to see if it shows up in mform.
     * @return bool
     */
    public function is_shown_in_mform(): bool {
        return true;
    }

    /**
     * Determines whether a particular item is currently available
     * according to this availability condition.
     * @param booking_option_settings $settings Item we're checking
     * @param int $userid User ID to check availability for
     * @param bool $not Set true if we are inverting the condition
     * @return bool True if available
     */
    public function is_available(booking_option_settings $settings, $userid, $not = false):bool {

        // This is the return value. Not available to begin with.
        $isavailable = false;

        if (!isset($this->customsettings->optionid)) {
            $isavailable = true;
        } else {
            $optionid = $this->customsettings->optionid;
            $optionsettings = singleton_service::get_instance_of_booking_option_settings($optionid);
            $bookinganswer = singleton_service::get_instance_of_booking_answers($optionsettings);
            $bookinginformation = $bookinganswer->return_all_booking_information($userid);

            if (isset($bookinginformation['iambooked'])) {
                $isavailable = true;
            }
        }

        // If it's inversed, we inverse.
        if ($not) {
            $isavailable = !$isavailable;
        }

        return $isavailable;
    }

    /**
     * Obtains a string describing this restriction (whether or not
     * it actually applies). Used to obtain information that is displayed to
     * students if the activity is not available to them, and for staff to see
     * what conditions are.
     *
     * The $full parameter can be used to distinguish between 'staff' cases
     * (when displaying all information about the activity) and 'student' cases
     * (when displaying only conditions they don't meet).
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param booking_option_settings $settings Item we're checking
     * @param int $userid User ID to check availability for
     * @param bool $not Set true if we are inverting the condition
     * @return array availability and Information string (for admin) about all restrictions on
     *   this item
     */
    public function get_description(booking_option_settings $settings, $userid = null, $full = false, $not = false):array {

        $description = '';

        $isavailable = $this->is_available($settings, $userid, $not);

        if ($isavailable) {
            $description = $full ? get_string('bo_cond_previouslybooked_full_available', 'mod_booking') :
                get_string('bo_cond_previouslybooked_available', 'mod_booking');
        } else {

            $url = new moodle_url('/mod/booking/optionview.php', [
                'optionid' => $this->customsettings->optionid,
                'cmid' => $settings->cmid
            ]);

            $description = $full ? get_string('bo_cond_previouslybooked_full_not_available',
                'mod_booking',
                $url->out(false)) :
                get_string('bo_cond_previouslybooked_not_available', 'mod_booking');
        }

        return [$isavailable, $description];
    }

    /**
     * Only customizable functions need to return their necessary form elements.
     *
     * @param MoodleQuickForm $mform
     * @param int $optionid
     * @return void
     */
    public function add_condition_to_mform(MoodleQuickForm &$mform, int $optionid = 0) {
        global $DB;

        // Check if PRO version is activated.
        if (wb_payment::pro_version_is_activated()) {

            $bookingoptionarray = [];
            if ($bookingoptionrecords = $DB->get_records_sql(
                "SELECT bo.id optionid, bo.titleprefix, bo.text optionname, b.name instancename
                FROM {booking_options} bo
                LEFT JOIN {booking} b
                ON bo.bookingid = b.id")) {
                foreach ($bookingoptionrecords as $bookingoptionrecord) {
                    if (!empty($bookingoptionrecord->titleprefix)) {
                        $bookingoptionarray[$bookingoptionrecord->optionid] =
                            "$bookingoptionrecord->titleprefix - $bookingoptionrecord->optionname " .
                                "($bookingoptionrecord->instancename)";
                    } else {
                        $bookingoptionarray[$bookingoptionrecord->optionid] =
                            "$bookingoptionrecord->optionname ($bookingoptionrecord->instancename)";
                    }
                }
            }

            $mform->addElement('checkbox', 'restrictwithpreviouslybooked',
                    get_string('restrictwithpreviouslybooked', 'mod_booking'));

            $previouslybookedoptions = [
                'tags' => false,
                'multiple' => false
            ];
            $mform->addElement('autocomplete', 'bo_cond_previouslybooked_optionid',
                get_string('bo_cond_previouslybooked_optionid', 'mod_booking'), $bookingoptionarray, $previouslybookedoptions);
            $mform->setType('bo_cond_previouslybooked_optionid', PARAM_INT);
            $mform->hideIf('bo_cond_previouslybooked_optionid', 'restrictwithpreviouslybooked', 'notchecked');

            $mform->addElement('checkbox', 'bo_cond_previouslybooked_overrideconditioncheckbox',
                get_string('overrideconditioncheckbox', 'mod_booking'));
            $mform->hideIf('bo_cond_previouslybooked_overrideconditioncheckbox', 'restrictwithpreviouslybooked', 'notchecked');

            $overrideoperators = [
                'AND' => get_string('overrideoperator:and', 'mod_booking'),
                'OR' => get_string('overrideoperator:or', 'mod_booking')
            ];
            $mform->addElement('select', 'bo_cond_previouslybooked_overrideoperator',
                get_string('overrideoperator', 'mod_booking'), $overrideoperators);
            $mform->hideIf('bo_cond_previouslybooked_overrideoperator',
                'bo_cond_previouslybooked_overrideconditioncheckbox', 'notchecked');

            $overrideconditions = bo_info::get_conditions(CONDPARAM_HARDCODED_ONLY);
            $overrideconditionsarray = [];
            foreach ($overrideconditions as $overridecondition) {
                // Remove the namespace from classname.
                $fullclassname = get_class($overridecondition); // With namespace.
                $classnameparts = explode('\\', $fullclassname);
                $shortclassname = end($classnameparts); // Without namespace.
                $overrideconditionsarray[$overridecondition->id] =
                    get_string('bo_cond_' . $shortclassname, 'mod_booking');
            }

            // Check for json conditions that might have been saved before.
            if (!empty($optionid) && $optionid > 0) {
                $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
                if (!empty($settings->availability)) {

                    $jsonconditions = json_decode($settings->availability);

                    if (!empty($jsonconditions)) {
                        foreach ($jsonconditions as $jsoncondition) {
                            // Currently conditions of the same type cannot be combined with each other.
                            if ($jsoncondition->id != BO_COND_JSON_PREVIOUSLYBOOKED) {
                                $overrideconditionsarray[$jsoncondition->id] = get_string('bo_cond_' .
                                    $jsoncondition->name, 'mod_booking');
                            }
                        }
                    }
                }
            }

            $mform->addElement('select', 'bo_cond_previouslybooked_overridecondition',
                get_string('overridecondition', 'mod_booking'), $overrideconditionsarray);
            $mform->hideIf('bo_cond_previouslybooked_overridecondition',
                'bo_cond_previouslybooked_overrideconditioncheckbox',
                'notchecked');
        } else {
            // No PRO license is active.
            $mform->addElement('static', 'restrictwithpreviouslybooked',
                get_string('restrictwithpreviouslybooked', 'mod_booking'),
                get_string('proversiononly', 'mod_booking'));
        }

        // Workaround: Only show, if it is not turned off in the option form config.
        // We currently need this, because html elements do not show up in the option form config.
        // In expert mode, we always show everything.
        $showhorizontalline = true;
        $formmode = get_user_preferences('optionform_mode');
        if ($formmode !== 'expert') {
            $cfgrestrictwithpreviouslybooked = $DB->get_field('booking_optionformconfig', 'active',
                ['elementname' => 'restrictwithpreviouslybooked']);
            if ($cfgrestrictwithpreviouslybooked === "0") {
                $showhorizontalline = false;
            }
        }
        if ($showhorizontalline) {
            $mform->addElement('html', '<hr class="w-50"/>');
        }
    }

    /**
     * Returns a condition object which is needed to create the condition JSON.
     *
     * @param stdClass $fromform
     * @return stdClass|null the object for the JSON
     */
    public function get_condition_object_for_json(stdClass $fromform): stdClass {
        $conditionobject = new stdClass;
        if (!empty($fromform->restrictwithpreviouslybooked)) {
            // Remove the namespace from classname.
            $classname = __CLASS__;
            $classnameparts = explode('\\', $classname);
            $shortclassname = end($classnameparts); // Without namespace.

            $conditionobject->id = BO_COND_JSON_PREVIOUSLYBOOKED;
            $conditionobject->name = $shortclassname;
            $conditionobject->class = $classname;
            $conditionobject->optionid = $fromform->bo_cond_previouslybooked_optionid;

            if (!empty($fromform->bo_cond_previouslybooked_overrideconditioncheckbox)) {
                $conditionobject->overrides = $fromform->bo_cond_previouslybooked_overridecondition;
                $conditionobject->overrideoperator = $fromform->bo_cond_previouslybooked_overrideoperator;
            }
        }
        // Might be an empty object if restriction is not set.
        return $conditionobject;
    }

    /**
     * Set default values to be shown in form when loaded from DB.
     * @param stdClass &$defaultvalues the default values
     * @param stdClass $acdefault the condition object from JSON
     */
    public function set_defaults(stdClass &$defaultvalues, stdClass $acdefault) {
        if (!empty($acdefault->optionid)) {
            $defaultvalues->restrictwithpreviouslybooked = "1";
            $defaultvalues->bo_cond_previouslybooked_optionid = $acdefault->optionid;
        }
        if (!empty($acdefault->overrides)) {
            $defaultvalues->bo_cond_previouslybooked_overrideconditioncheckbox = "1";
            $defaultvalues->bo_cond_previouslybooked_overridecondition = $acdefault->overrides;
            $defaultvalues->bo_cond_previouslybooked_overrideoperator = $acdefault->overrideoperator;
        }
    }
}

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
 * WunderByte javascript library/framework
 *
 * @module mod_booking/wunderbyte
 * @package mod_booking
 * @copyright 2023 Kamil Hurajt <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {WunderByteJS} from "mod_booking/wunderbyte";

export function electiveSorting() {
    let options = {
        items: 'li.list-group-item',
        container: 'ul#wb-sortabe'
    };

    let wunderbyteJS = new WunderByteJS();
    wunderbyteJS.sortable(options);
}
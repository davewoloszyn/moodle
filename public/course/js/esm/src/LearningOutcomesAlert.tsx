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
 * A banner nudging teachers to set up learning outcomes in their course, which can optionally be dismissed.
 *
 * @module     core_course/LearningOutcomesAlert
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Alert, Link} from '@moodlehq/design-system';
import {fetchOne} from '@moodle/lms/core/ajax';

export type LearningOutcomesAlertProps = {
    /** The banner message. */
    message: string;
    /**
     * The name of the user preference to set when the banner is dismissed, so that it stays dismissed.
     * The banner can only be dismissed when this is set.
     */
    preference?: string;
    /** The accessible label of the dismiss button. */
    dismisslabel?: string;
    /** The text of the link shown below the message. The link is only shown when both this and linkurl are set. */
    linklabel?: string;
    /** The URL the link takes the user to. */
    linkurl?: string;
};

/**
 * Render a banner. If it has a preference, it can be dismissed and then stays dismissed for the user.
 *
 * @param props Component props.
 * @returns The rendered banner.
 */
export default function LearningOutcomesAlert({
    message,
    preference,
    dismisslabel,
    linklabel,
    linkurl,
}: LearningOutcomesAlertProps) {
    const isDismissible = preference !== undefined && preference !== '';
    const hasLink = linklabel !== undefined && linklabel !== '' && linkurl !== undefined && linkurl !== '';

    const handleDismiss = () => {
        if (!isDismissible) {
            return;
        }

        // If this fails, the banner is simply shown again on the next page load.
        fetchOne({
            methodname: 'core_user_update_user_preferences',
            args: {preferences: [{type: preference, value: '1'}]},
        }).catch(() => undefined);
    };

    return (
        <Alert
            type='info'
            message={message}
            isDismissible={isDismissible}
            dismissAriaLabel={dismisslabel}
            onDismiss={handleDismiss}
            className='my-3'
        >
            {hasLink && <Link label={linklabel} href={linkurl} />}
        </Alert>
    );
}

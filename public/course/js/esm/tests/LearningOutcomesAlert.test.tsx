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
 * Jest tests for the LearningOutcomesAlert component.
 *
 * @module     core_course/tests/LearningOutcomesAlert
 * @copyright  2026 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactNode} from 'react';
import {fireEvent, render, screen} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import LearningOutcomesAlert from '../src/LearningOutcomesAlert';

// @moodlehq/design-system is ESM only, so Jest cannot resolve it from a CommonJS test bundle.
// Stand Alert and Link in with markup exposing the props these tests rely on.
jest.mock('@moodlehq/design-system', () => ({
    Alert: ({type, message, isDismissible = true, dismissAriaLabel, onDismiss, children}: {
        type: string;
        message: string;
        isDismissible?: boolean;
        dismissAriaLabel?: string;
        onDismiss?: () => void;
        children?: ReactNode;
    }) => (
        <div data-testid="alert" data-type={type}>
            {message}
            {children}
            {isDismissible && <button type="button" aria-label={dismissAriaLabel} onClick={onDismiss} />}
        </div>
    ),
    Link: ({label, href}: {label: string; href: string}) => <a href={href}>{label}</a>,
}), {virtual: true});

jest.mock('@moodle/lms/core/ajax', () => ({
    fetchOne: jest.fn(() => Promise.resolve()),
}));

const props = {
    message: 'Did you know you can add learning outcomes to this course?',
    preference: 'core_course_learningoutcomes_setup_dismissed_2',
    dismisslabel: 'Dismiss this notification',
};

describe('LearningOutcomesAlert', () => {
    it('renders an info alert with the message', () => {
        render(<LearningOutcomesAlert {...props} />);

        const alert = screen.getByTestId('alert');
        expect(alert).toHaveTextContent(props.message);
        expect(alert).toHaveAttribute('data-type', 'info');
    });

    it('does not show a link without both a link label and URL', () => {
        render(<LearningOutcomesAlert {...props} linklabel="Set up learning outcomes" />);

        expect(screen.queryByRole('link')).toBeNull();
    });

    it('shows a link to the given URL inside the alert', () => {
        const url = 'https://example.com/course/learningoutcomes.php?id=2';
        render(<LearningOutcomesAlert {...props} linklabel="Set up learning outcomes" linkurl={url} />);

        const link = screen.getByRole('link', {name: 'Set up learning outcomes'});
        expect(link).toHaveAttribute('href', url);
        expect(screen.getByTestId('alert')).toContainElement(link);
    });

    it('saves the user preference when dismissed', () => {
        render(<LearningOutcomesAlert {...props} />);
        expect(fetchOne).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', {name: 'Dismiss this notification'}));

        expect(fetchOne).toHaveBeenCalledTimes(1);
        expect(fetchOne).toHaveBeenCalledWith({
            methodname: 'core_user_update_user_preferences',
            args: {preferences: [{type: props.preference, value: '1'}]},
        });
    });

    it('cannot be dismissed without a preference', () => {
        render(<LearningOutcomesAlert message="Your organisation recommends at least 3 learning outcomes for each course." />);

        expect(screen.getByTestId('alert')).toBeInTheDocument();
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('ignores a failure to save the user preference', async() => {
        const failure = Promise.reject(new Error('Network error'));
        (fetchOne as jest.Mock).mockReturnValueOnce(failure);
        render(<LearningOutcomesAlert {...props} />);

        fireEvent.click(screen.getByRole('button', {name: 'Dismiss this notification'}));

        await expect(failure).rejects.toThrow('Network error');
    });
});

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
 * JS module for toggling the sensitive input visibility (e.g. passwords, keys).
 *
 * @module     core/togglesensitive
 * @copyright  2023 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    BUTTON: '#toggledarkmode',
    ICON: '#toggledarkmode .icon',
};

// const PIX = {
//     EYE: 't/hide',
//     EYE_SLASH: 't/show',
// };


/**
 * Entrypoint of the js.
 *
 * @method init
 */
export const init = () => {
    window.console.log('zzz');
    registerListenerEvents();
};


/**
 * Register event listeners.
 *
 * @method registerListenerEvents
 */
const registerListenerEvents = () => {

    // Toggle the sensitive input visibility when interacting with the toggle button.
    document.addEventListener('click', (event) => {
        const toggleButton = event.target.closest(SELECTORS.BUTTON);
        if (toggleButton) {
            window.console.log('xxxxx');
        }
    });

};

// /**
//  * Toggle the sensitive input visibility and its associated icon.
//  *
//  * @method toggleSensitiveVisibility
//  * @param {HTMLInputElement} sensitiveInput The sensitive input element.
//  * @param {HTMLElement} toggleButton The toggle button.
//  * @param {boolean} force Force the input back to password type.
//  */
// const toggleSensitiveVisibility = (sensitiveInput, toggleButton, force = false) => {
//     const pendingPromise = new Pending('core/togglesensitive:toggle');
//     let type;
//     let icon;
//     if (force === true) {
//         type = 'password';
//         icon = PIX.EYE;
//     } else {
//         type = sensitiveInput.getAttribute('type') === 'password' ? 'text' : 'password';
//         icon = sensitiveInput.getAttribute('type') === 'password' ? PIX.EYE_SLASH : PIX.EYE;
//     }
//     sensitiveInput.setAttribute('type', type);
//     Templates.renderPix(icon, 'core').then((icon) => {
//         toggleButton.innerHTML = icon;
//         pendingPromise.resolve();
//         return;
//     }).catch(Notification.exception);
// };

// This file is part of Moodle - http://moodle.org/ //
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
 * AI provider model selection handler.
 *
 * @module     aiprovider_openai/modelchooser
 * @copyright  2025 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const Selectors = {
    fields: {
        selector: '[data-modelchooser-field="selector"]',
        updateButton: '[data-modelchooser-field="updateButton"]',
        modelSettings: '#id_modelsettingsheader input, #id_modelsettingsheader textarea, #id_modelsettingsheader select',
    },
};

/**
 * Initialise the AI provider chooser.
 *
 * @param {String} currentModel The current model saved in action config.
 */
export const init = (currentModel) => {
    const modelSelector = document.querySelector(Selectors.fields.selector);
    if (modelSelector) {
        modelSelector.addEventListener('change', e => {
            modelSelector.options[e.target.selectedIndex].selected = true;
            const form = e.target.closest('form');
            const updateButton = form.querySelector(Selectors.fields.updateButton);
            updateButton.click();
        });

        // If we have changed models, clear all action settings.
        if (currentModel !== modelSelector.value) {
            clearActionSettings();
        }
    }
};

/**
 * Reset all action settings inputs.
 */
const clearActionSettings = () => {
    document.querySelectorAll(Selectors.fields.modelSettings).forEach(el => {
        if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = false;
        } else if (el.tagName === 'SELECT') {
            el.selectedIndex = 0;
        } else {
            el.value = '';
        }
    });
};

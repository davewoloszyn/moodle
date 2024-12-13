import $ from 'jquery';
import log from 'core/log';
import cloudpoodll from './cloudpoodllloader';
import * as str from 'core/str';

log.debug('cloudpoodll feedback helper: initialising');

const instancemap = {};

export default class feedbackHandler {

    togglestate = 0;
    strings = {};
    controls = {};
    registeredtoggler = false;

    constructor(opts) {
        this.component = opts['component'];
        this.subtype = opts['subtype'] || '';

        this.register_controls();
        this.register_events();
    }

    static init(opts) {
        return new feedbackHandler(opts);
    }



    register_controls() {
        this.controls.deletebutton = $('.' + this.component + this.subtype + '_deletefeedbackbutton');
        this.controls.updatecontrol = $('#' + this.component + this.subtype + '_updatecontrol');
        this.controls.currentcontainer = $('.' + this.component + this.subtype + '_currentfeedback');
        this.controls.togglecontainer = $('.' + this.component + this.subtype + '_togglecontainer');
        this.controls.togglebutton = $('.' + this.component + this.subtype + '_togglecontainer .togglebutton');
        this.controls.toggletext = $('.' + this.component + this.subtype + '_togglecontainer .toggletext');
        str.get_string('clicktohide', this.component).done(s => {
            this.strings['clicktohide'] = s;
        });
        str.get_string('clicktoshow', this.component).done(s => {
            this.strings['clicktoshow'] = s;
        });
    }

    register_events() {
        this.controls.deletebutton.click(() => {
            if (this.controls.updatecontrol) {
                if (confirm(M.util.get_string('reallydeletefeedback', this.component))) {
                    this.controls.updatecontrol.val(-1);
                    this.controls.currentcontainer.html('');
                }
            }
        });
        this.controls.togglebutton.click(this.toggle_currentfeedback.bind(this));
        this.controls.toggletext.click(this.toggle_currentfeedback.bind(this));
    }

    toggle_currentfeedback() {
        const doToggleState = () => {
            if (this.togglestate == 0) {
                this.controls.togglebutton.removeClass('fa-toggle-off');
                this.controls.togglebutton.addClass('fa-toggle-on');
                this.controls.toggletext.text(this.strings['clicktohide']);
                this.togglestate = 1;
            } else {
                this.controls.togglebutton.removeClass('fa-toggle-on');
                this.controls.togglebutton.addClass('fa-toggle-off');
                this.controls.toggletext.text(this.strings['clicktoshow']);
                this.togglestate = 0;
            }
        };
        this.controls.currentcontainer.toggle(
            {duration: 300, complete: doToggleState}
        );
        return false;
    }

    static registerToggler() {
        if (this.registeredtoggler) {
            return;
        }
        this.registeredtoggler = true;
        document.addEventListener('click', e => {
            const toggleinput = e.target.closest('[data-action="toggle"]');
            if (toggleinput) {
                try {
                    const togglecontainer = document.querySelector(toggleinput.dataset.target);
                    if (togglecontainer) {
                        const labelElement = toggleinput.closest('label.togglerecorder');
                        const $togglecontainer = $(togglecontainer);
                        if (toggleinput.checked) {
                            $togglecontainer.collapse('show');
                            labelElement.classList.add('enabledstate');
                        } else {
                            $togglecontainer.collapse('hide');
                            labelElement.classList.remove('enabledstate');
                        }
                    }
                } catch (e) {
                    //do nothing
                    log.debug(e);
                }
            }
        });

    }
}// end of return object.
import $ from 'jquery';
import log from 'core/log';
import cloudpoodll from './cloudpoodllloader';
import * as str from 'core/str';

log.debug('cloudpoodll feedback setup recorder: initialising');

const instancemap = {};

export default class setuprecorder {

    uploadstate = false;
    strings = {};
    controls = {};

    constructor(opts) {
        this.component = opts['component'];
        this.subtype = opts['subtype'] || '';

        this.register_controls();
        this.register_events();
        this.setup_recorder();
    }

    static init(opts) {
        return new setuprecorder(opts);
    }

    setup_recorder() {
        const recorderid = this.component + this.subtype + '_therecorder';
        instancemap[recorderid] = Object.assign({}, this);
        const recorder_callback = evt => {
            if (instancemap.hasOwnProperty(evt.id)) {
                const instance = instancemap[evt.id];
                switch (evt.type) {
                    case 'recording':
                        if (evt.action === 'started') {
                            instance.controls.updatecontrol.val();
                        }
                        break;
                    case 'awaitingprocessing':
                        if (instance.uploadstate != 'posted') {
                            instance.controls.updatecontrol.val(evt.mediaurl);
                        }
                        instance.uploadstate = 'posted';
                        break;
                    case 'error':
                        alert('PROBLEM:' + evt.message);
                        break;
                }
            }
        };
        this.uploadstate = false;
        cloudpoodll.init(recorderid, recorder_callback);
    }

    register_controls() {
        this.controls.updatecontrol = $('#' + this.component + this.subtype + '_updatecontrol');
    }

    register_events() {
        return;
    }

}// end of return object.
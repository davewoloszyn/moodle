import $ from 'jquery';
import log from 'core/log';


log.debug('loom loader: initialising');


export default class loomloader {

    constructor(opts) {
        this.component = opts['component'];
        this.subtype = opts['subtype'] || '';
    }
    static init(opts) {
        return new loomloader(opts);
    }
}
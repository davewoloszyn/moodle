define(['jquery', 'core/log'], function ($, log) {
    "use strict"; // jshint ;_;

    log.debug('Standard Transcript: initialising');

    return {

        init: function (opts) {

            var component = opts['component'];
            var playerid = opts['playerid'];
            var containerid = opts['containerid'];
            var cssprefix = opts['cssprefix'];
            var config = {};
            config.settings = {};
            if (playerid && $('#' + playerid).length) {
                config.component = component;
                config.container = $('#' + containerid)
                config.prefix = cssprefix;
                config.player = $('#' + playerid)[0];
                config.transcripturl = opts['transcripturl'];
                config.title = M.util.get_string('transcripttitle', component);
                this.fetch_transcript(config);

            }
        },

        fetch_transcript: function (config) {
            var header = '<header class="' + config.prefix + '-header">' + config.title + '</header>';
            config.container.load(config.transcripturl, function () {
                config.container.prepend(header);
            });
        }

    };// end of return object
});
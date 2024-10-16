(function (window, $) {
    'use strict';

    const ImedgeSelect2Handler = function (icinga) {
        this.icinga = icinga;
    };

    ImedgeSelect2Handler.prototype = {
        initialize: function () {
            const _this = this;
            $(document).on('select2:open', function (e) {
                window.setTimeout(function () {
                    document.querySelector('input.select2-search__field').focus();
                }, 0);
            });
            $(document).on('rendered', '.container', this.rendered.bind(_this));
            $(document).on('beforerender', '.container', this.beforeRender.bind(_this));
            $(document).on('keypress', function (ev) {
                let $element = $(ev.target);
                if ($element.is('span.select2-selection')) {
                    $element.closest('.select2-container').find('select').select2('open');
                }
            });
        },

        rendered: function (ev) {
            let $container = $(ev.currentTarget);
            let _this = this;
            $container.find('select.imedge-select2:not(.select2-hidden-accessible)').each(function (idx, element) {
                _this.initializeSelect2($(element), $container);
            });
        },

        beforeRender: function (ev) {
            let $container = $(ev.currentTarget);
            $container.find('select.select2-hidden-accessible').select2('destroy');
        },

        initializeSelect2: function ($element, $container) {
            let placeholder = $element.attr('placeholder');
            let params = {
                dropdownParent: $container
            };
            if (placeholder) {
                params.allowClear = true;
                params.placeholder = placeholder;
            }
            let dataUrl = $element.data('lookupUrl');
            if (dataUrl) {
                params.ajax = {
                    url: dataUrl,
                    delay: 100,
                    data: function (params) {
                        // Query parameters will be ?search=[term]&type=public
                        return {
                            search: params.term,
                            page: params.page || 1
                            // type: 'public'
                            // TODO: eventually add context
                        }
                    }
                }
            }
            $element.select2(params);
        },
    };

    let startup;
    let attempt = 0;
    const w = window;
    function launch(icinga)
    {
        w.imedgeSelect2 = new ImedgeSelect2Handler(icinga);
    }

    function safeLaunch()
    {
        attempt++;
        if (typeof(w.icinga) !== 'undefined' && w.icinga.initialized) {
            clearInterval(startup);
            launch(w.icinga);
            w.icinga.logger.info('Select2 integration is ready\'');
        } else {
            if (attempt === 3) {
                console.log('Select2 integration is still waiting for icinga');
            }
        }
    }

    $(document).ready(function () {
        startup = setInterval(safeLaunch, 150);
        setTimeout(safeLaunch, 1);
    });

})(window, jQuery);

define(function(require, exports, module) {
    'use strict';

    const _ = require('underscore');
    const __ = require('orotranslation/js/translator');
    const BasePlugin = require('oroui/js/app/plugins/base/plugin');
    const ShowComponentAction = require('oro/datagrid/action/show-component-action');
    const ToolbarMassActionComponent = require('orodatagrid/js/app/components/toolbar-mass-action-component');

    let config = require('module-config').default(module.id);
    config = _.extend({
        icon: 'ellipsis-h',
        wrapperClassName: 'toolbar-mass-actions',
        label: __('oro.datagrid.mass_action.title'),
        attributes: {'data-placement': 'bottom-end'}
    }, config);

    const ToolbarMassActionPlugin = BasePlugin.extend({
        enable: function() {
            this.listenTo(this.main, 'beforeToolbarInit', this.onBeforeToolbarInit);
            ToolbarMassActionPlugin.__super__.enable.call(this);
        },

        onBeforeToolbarInit: function(toolbarOptions) {
            const options = {
                datagrid: this.main,
                launcherOptions: _.extend(config, {
                    componentConstructor: ToolbarMassActionComponent,
                    collection: toolbarOptions.collection,
                    actions: this.main.massActions
                })
            };

            toolbarOptions.actions.push(new ShowComponentAction(options));
        }
    });

    return ToolbarMassActionPlugin;
});

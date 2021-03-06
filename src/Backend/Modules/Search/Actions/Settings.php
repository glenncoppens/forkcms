<?php

namespace Backend\Modules\Search\Actions;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Backend\Core\Engine\Base\ActionEdit as BackendBaseActionEdit;
use Backend\Core\Engine\Form as BackendForm;
use Backend\Core\Engine\Language as BL;
use Backend\Core\Engine\Model as BackendModel;
use Backend\Modules\Search\Engine\Model as BackendSearchModel;

/**
 * This is the settings-action, it will display a form to set general search settings
 *
 * @author Matthias Mullie <forkcms@mullie.eu>
 */
class Settings extends BackendBaseActionEdit
{
    /**
     * List of modules
     *
     * @var    array
     */
    private $modules = array();

    /**
     * Settings per module
     *
     * @var    array
     */
    private $settings = array();

    /**
     * Execute the action
     */
    public function execute()
    {
        parent::execute();
        $this->loadForm();
        $this->validateForm();
        $this->parse();
        $this->display();
    }

    /**
     * Loads the settings form
     */
    private function loadForm()
    {
        // init settings form
        $this->frm = new BackendForm('settings');

        // get current settings
        $this->settings = BackendSearchModel::getModuleSettings();

        // add field for pagination
        $this->frm->addDropdown(
            'overview_num_items',
            array_combine(range(1, 30), range(1, 30)),
            $this->get('fork.settings')->get($this->getModule(), 'overview_num_items', 20)
        );
        $this->frm->addDropdown(
            'autocomplete_num_items',
            array_combine(range(1, 30), range(1, 30)),
            $this->get('fork.settings')->get($this->getModule(), 'autocomplete_num_items', 20)
        );
        $this->frm->addDropdown(
            'autosuggest_num_items',
            array_combine(range(1, 30), range(1, 30)),
            $this->get('fork.settings')->get($this->getModule(), 'autosuggest_num_items', 20)
        );

        // add checkbox for the sitelinks search box in Google
        $this->frm->addCheckbox(
            'use_sitelinks_search_box',
            $this->get('fork.settings')->get($this->getModule(), 'use_sitelinks_search_box', true)
        );

        // modules that, no matter what, can not be searched
        $disallowedModules = array('Search');

        // loop modules
        foreach (BackendModel::getModulesForDropDown() as $module => $label) {
            // check if module is searchable
            if (!in_array($module, $disallowedModules) &&
                method_exists('Frontend\\Modules\\' . $module . '\\Engine\\Model', 'search')
            ) {
                // add field to decide whether or not this module is searchable
                $this->frm->addCheckbox(
                    'search_' . $module,
                    isset($this->settings[$module]) ? $this->settings[$module]['searchable'] == 'Y' : false
                );

                // add field to decide weight for this module
                $this->frm->addText(
                    'search_' . $module . '_weight',
                    isset($this->settings[$module]) ? $this->settings[$module]['weight'] : 1
                );

                // field disabled?
                if (!isset($this->settings[$module]) || $this->settings[$module]['searchable'] != 'Y') {
                    $this->frm->getField('search_' . $module . '_weight')->setAttribute('disabled', 'disabled');
                    $this->frm->getField('search_' . $module . '_weight')->setAttribute('class', 'inputText disabled');
                }

                // add to list of modules
                $this->modules[] = array(
                    'module' => $module,
                    'id' => $this->frm->getField('search_' . $module)->getAttribute('id'),
                    'label' => $label,
                    'chk' => $this->frm->getField('search_' . $module)->parse(),
                    'txt' => $this->frm->getField('search_' . $module . '_weight')->parse(),
                    'txtError' => ''
                );
            }
        }
    }

    /**
     * Parse the form
     */
    protected function parse()
    {
        parent::parse();

        // parse form
        $this->frm->parse($this->tpl);

        // assign iteration
        $this->tpl->assign(array('modules' => $this->modules));
    }

    /**
     * Validates the settings form
     */
    private function validateForm()
    {
        // form is submitted
        if ($this->frm->isSubmitted()) {
            // validate module weights
            foreach ($this->modules as $i => $module) {
                // only if this module is enabled
                if ($this->frm->getField('search_' . $module['module'])->getChecked()) {
                    // valid weight?
                    $this->frm->getField('search_' . $module['module'] . '_weight')->isDigital(
                        BL::err('WeightNotNumeric')
                    );
                    $this->modules[$i]['txtError'] = $this->frm->getField(
                        'search_' . $module['module'] . '_weight'
                    )->getErrors();
                }
            }

            // form is validated
            if ($this->frm->isCorrect()) {
                // set our settings
                $this->get('fork.settings')->set(
                    $this->getModule(),
                    'overview_num_items',
                    $this->frm->getField('overview_num_items')->getValue()
                );
                $this->get('fork.settings')->set(
                    $this->getModule(),
                    'autocomplete_num_items',
                    $this->frm->getField('autocomplete_num_items')->getValue()
                );
                $this->get('fork.settings')->set(
                    $this->getModule(),
                    'autosuggest_num_items',
                    $this->frm->getField('autosuggest_num_items')->getValue()
                );
                $this->get('fork.settings')->set(
                    $this->getModule(),
                    'use_sitelinks_search_box',
                    $this->frm->getField('use_sitelinks_search_box')->isChecked()
                );

                // module search
                foreach ((array) $this->modules as $module) {
                    $searchable = $this->frm->getField('search_' . $module['module'])->getChecked() ? 'Y' : 'N';
                    $weight = $this->frm->getField('search_' . $module['module'] . '_weight')->getValue();

                    // insert, or update
                    BackendSearchModel::insertModuleSettings($module, $searchable, $weight);
                }

                // trigger event
                BackendModel::triggerEvent($this->getModule(), 'after_changed_settings');

                // redirect to the settings page
                $this->redirect(BackendModel::createURLForAction('Settings') . '&report=saved');
            }
        }
    }
}

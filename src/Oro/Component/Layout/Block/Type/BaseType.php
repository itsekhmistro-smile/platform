<?php

namespace Oro\Component\Layout\Block\Type;

use Oro\Component\Layout\Block\OptionsResolver\OptionsResolver;
use Oro\Component\Layout\BlockInterface;
use Oro\Component\Layout\BlockView;
use Oro\Component\Layout\Block\Type\Options;

class BaseType extends AbstractType
{
    const NAME = 'block';

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setDefined([
            'vars',
            'attr',
            'label',
            'label_attr',
            'translation_domain',
            'class_prefix',
            'additional_block_prefix'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(BlockView $view, BlockInterface $block, Options $options)
    {
        // merge the passed variables with the existing ones
        if (!empty($options['vars'])) {
            $replaced = array_replace($view->vars->toArray(), $options['vars']->toArray());
            $view->vars = new Options($replaced);
        }

        // add the view to itself vars to allow get it using 'block' variable in a rendered, for example TWIG
        $view->vars['block'] = $view;

        $view->vars['class_prefix'] = null;
        if ($options->offsetExists('class_prefix')) {
            $view->vars['class_prefix'] = $options['class_prefix'];
        } elseif ($view->parent) {
            $view->vars['class_prefix'] = $view->parent->vars['class_prefix'];
        }

        // replace attributes if specified ('attr' variable always exists in a view because it is added by FormView)
        if (isset($options['attr'])) {
            $view->vars['attr'] = $options['attr'];
        }

        // add label text and attributes if specified
        if (isset($options['label'])) {
            $view->vars['label'] = $options['label'];
            $view->vars['label_attr'] = [];
            if (isset($options['label_attr'])) {
                $view->vars['label_attr'] = $options['label_attr'];
            }
        }

        // add the translation domain
        $view->vars['translation_domain'] = $this->getTranslationDomain($view, $options);

        // add core variables to the block view, like id, block type and variables required for rendering engine
        $id   = $block->getId();
        $name = $block->getTypeName();

        // the block prefix must contain only letters, numbers and underscores (_)
        // due to limitations of block names in TWIG
        $uniqueBlockPrefix = '_' . preg_replace('/[^a-z0-9_]+/i', '_', $id);
        $blockPrefixes     = $block->getTypeHelper()->getTypeNames($name);
        if (isset($options['additional_block_prefix'])) {
            $blockPrefixes[] = $options['additional_block_prefix'];
        }
        $blockPrefixes[]   = $uniqueBlockPrefix;

        $view->vars['id']                   = $id;
        $view->vars['block_type']           = $name;
        $view->vars['block_type_widget_id'] = $name . '_widget';
        $view->vars['unique_block_prefix']  = $uniqueBlockPrefix;
        $view->vars['block_prefixes']       = $blockPrefixes;
        $view->vars['cache_key']            = sprintf('_%s_%s', $id, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(BlockView $view, BlockInterface $block, Options $options)
    {
        $vars = $view->vars->toArray();
        if (isset($vars['attr']['id']) && !isset($vars['label_attr']['for'])) {
            $view->vars['label_attr']['for'] = $vars['attr']['id'];
        }

        $view->vars['blocks'] = $view->blocks;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @param BlockView $view
     * @param Options   $options
     *
     * @return string
     */
    protected function getTranslationDomain(BlockView $view, Options $options)
    {
        $translationDomain = isset($options['translation_domain'])
            ? $options['translation_domain']
            : null;
        if (!$translationDomain && $view->parent) {
            $translationDomain = $view->parent->vars['translation_domain'];
        }
        if (!$translationDomain) {
            $translationDomain = 'messages';
        }

        return $translationDomain;
    }
}

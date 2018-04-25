<?php

namespace Oro\Bundle\TranslationBundle\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\TranslationBundle\Form\ChoiceList\TranslationChoiceLoader;
use Oro\Bundle\TranslationBundle\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TranslatableEntityType extends AbstractType
{
    const NAME = 'translatable_entity';

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var ChoiceListFactoryInterface
     */
    protected $factory;

    /**
     * @param ManagerRegistry $registry
     * @param ChoiceListFactoryInterface $choiceListFactory
     */
    public function __construct(ManagerRegistry $registry, ChoiceListFactoryInterface $choiceListFactory)
    {
        $this->registry = $registry;
        $this->factory = $choiceListFactory;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['multiple']) {
            $builder->addEventSubscriber(new MergeDoctrineCollectionListener())
                ->addViewTransformer(new CollectionToArrayTransformer(), true);
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'property'      => null,
                'query_builder' => null,
                'choices'       => null,
                'translatable_options' => false
            )
        );

        $resolver->setRequired(array('class'));

        $resolver->setNormalizer('choice_label', function (Options $options, $value) {
            if ($value) {
                return $value;
            }

            return $options['property'] ?? null;
        });

        //TODO: Remove in scope of BAP-15236
        $resolver->setNormalizer('choices_as_values', function () {
            return true;
        });

        $resolver->setNormalizer('choice_value', function (Options $options, $value) {
            if ($value) {
                return $value;
            }

            return $this->registry->getManager()->getClassMetadata($options['class'])->getSingleIdentifierFieldName();
        });

        $resolver->setNormalizer('choice_loader', function (Options $options) {
            if (null !== $options['choices']) {
                return null;
            }

            return new TranslationChoiceLoader(
                $options['class'],
                $this->registry,
                $this->factory,
                $options['query_builder']
            );
        });
    }
}

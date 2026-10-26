<?php

namespace Irstea\BdohAdminBundle\Form\Type;

use Irstea\BdohAdminBundle\Form\DataTransformer\TheiaThesaurusTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TheiaThesaurusType extends AbstractType
{
    /** @var string */
    private $thesaurusUrl;

    public function __construct(string $thesaurusUrl)
    {
        $this->thesaurusUrl = $thesaurusUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setCompound(false);
        $builder->addViewTransformer(new TheiaThesaurusTransformer());
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['thesaurusUrl'] = $options['thesaurusUrl'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiple'     => false,
            'thesaurusUrl' => $this->thesaurusUrl,
        ]);

        $resolver->setAllowedTypes('multiple', ['bool']);
        $resolver->setAllowedTypes('thesaurusUrl', ['string']);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'bdoh_theiathesaurus';
    }
}

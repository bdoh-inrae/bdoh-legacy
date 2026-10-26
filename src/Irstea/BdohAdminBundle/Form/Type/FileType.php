<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Form\Type;

use Irstea\BdohAdminBundle\Form\DataTransformer\FileTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType as BaseFileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FileType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $newFileOptions = $childOptions = [
            'error_bubbling' => true,
            'required'       => false,
        ];
        if (!empty($options['accept'])) {
            $newFileOptions['attr']['accept'] = $options['accept'];
        }

        $builder
            ->add('current', HiddenType::class, $childOptions)
            ->add('new', BaseFileType::class, $newFileOptions)
            ->addModelTransformer(new FileTransformer());
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['accept'] = empty($options['accept']) ? '' : $options['accept'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('accept', '')
            ->addAllowedTypes('accept', ['string', 'array'])
            ->setNormalizer(
                'accept',
                function (OptionsResolver $resolver, $value) {
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }

                    return $value;
                }
            );
    }

    public function getBlockPrefix(): string
    {
        return 'bdoh_file';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return FormType::class;
    }
}

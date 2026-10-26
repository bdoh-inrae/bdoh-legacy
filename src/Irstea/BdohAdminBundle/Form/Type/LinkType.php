<?php

namespace Irstea\BdohAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\FixUrlProtocolListener;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Le lien d'un observatoire ou d'un partenaire, comme le champ url de Symfony 2.8 : rendu en
 * type="url", et http:// ajouté à une adresse saisie sans protocole (www.inrae.fr, mais aussi
 * javascript:…, qui devient ainsi inoffensif). Le UrlType de Symfony 7 n'ajoute le protocole qu'en
 * se rendant en type="text".
 */
final class LinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new FixUrlProtocolListener('http'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('default_protocol', null);
    }

    public function getParent(): ?string
    {
        return UrlType::class;
    }
}

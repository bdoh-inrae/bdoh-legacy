<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Validator\Constraint;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Constraint largely based on Symfony\Component\Security\Core\Validator\Constraint\UserPassword.
 *
 * The only difference is we force the retrieval of 'password' and 'salt' fields, in database.
 *
 * @DI\Service("irstea_bdoh_security.validator.user_password")
 * @DI\Tag("validator.constraint_validator", attributes={"alias" = "irstea_bdoh_security.validator.user_password"})
 */
class UserPasswordValidator extends ConstraintValidator
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * UserPasswordValidator constructor.
     *
     * @param EntityManager           $entityManager
     * @param TokenStorageInterface   $tokenStorage
     * @param EncoderFactoryInterface $encoderFactory
     *
     * @DI\InjectParams({
     *     "entityManager" = @DI\Inject("doctrine.orm.entity_manager"),
     *     "tokenStorage" = @DI\Inject("security.token_storage"),
     *     "encoderFactory" = @DI\Inject("security.encoder_factory"),
     * })
     */
    public function __construct(
        EntityManager $entityManager,
        TokenStorageInterface $tokenStorage,
        EncoderFactoryInterface $encoderFactory
    ) {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($newPassword, Constraint $constraint)
    {
        if (empty($newPassword)) {
            return;
        }

        // Gets the connected User
        $user = $this->tokenStorage->getToken()->getUser();

        if (!$user instanceof Utilisateur) {
            throw new ConstraintDefinitionException('The User must be an instance of Utilisateur');
        }

        list($password, $salt) = $this->entityManager->getRepository('Irstea\BdohSecurityBundle\Entity\Utilisateur')->getPasswordSalt($user);

        $encoder = $this->encoderFactory->getEncoder($user);

        if (!$encoder->isPasswordValid($password, $newPassword, $salt)) {
            $this->context->addViolation($constraint->message);
        }
    }
}

<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Validator\Constraint;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Constraint largely based on Symfony\Component\Security\Core\Validator\Constraint\UserPassword.
 *
 * The only difference is we force the retrieval of 'password' and 'salt' fields, in database.
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
     * @var PasswordHasherFactoryInterface
     */
    protected $hasherFactory;

    /**
     * UserPasswordValidator constructor.
     *
     * @param EntityManager           $entityManager
     * @param TokenStorageInterface   $tokenStorage
     * @param PasswordHasherFactoryInterface $hasherFactory
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        PasswordHasherFactoryInterface $hasherFactory
    ) {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->hasherFactory = $hasherFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($newPassword, Constraint $constraint): void
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

        // Le hacheur des comptes (security.yaml) ; le sel ne sert qu'aux anciens hachages qui en ont
        $hasher = $this->hasherFactory->getPasswordHasher($user);

        if (!$hasher->verify($password, $newPassword, $salt)) {
            $this->context->addViolation($constraint->message);
        }
    }
}

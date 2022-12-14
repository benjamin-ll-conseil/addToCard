<?php

namespace App\Tests\Entity;

use App\Entity\Users;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolation;

class UsersTest extends KernelTestCase
{
    public function getEntity(): Users
    {
         $user = new Users();
        return $user->setLastname('Doe')
            ->setFirstname('John')
            ->setPassword('testDuPassword')
            ->setEmail('test@test.com');
    }

    public function assertHasErrors(Users $user, int $number = 0)
    {
        self::bootKernel();
        $container = static::getContainer();
        $errors= $container->get('validator')->validate($user);
        
        $messages = [];
        /** @var ConstraintViolation $error */
        foreach($errors as $error) {
            $messages[] = $error->getPropertyPath() . '=> ' . $error->getMessage();
        }
        $this->assertCount($number, $errors, implode(', ', $messages));
    }

    public function testValidNewUser(): void
    {
        $this->assertHasErrors($this->getEntity(), 0);
    }
    
    public function testInValidUserMail(): void
    {
        $this->assertHasErrors($this->getEntity()->setEmail(''), 1);
    }
    
    public function testInValidUserfirstName(): void
    {
        $this->assertHasErrors($this->getEntity()->setFirstname(''), 1);
    }

    public function testInValidUserLastName(): void
    {
        $this->assertHasErrors($this->getEntity()->setLastname(''), 1);
    }
}

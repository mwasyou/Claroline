<?php

namespace Claroline\CoreBundle\Security;

use Claroline\CoreBundle\Entity\Role;
use Claroline\CoreBundle\Testing\TransactionalTestCase;

class RoleManagerTest extends TransactionalTestCase
{
    private $roleManager;
    
    public function setUp()
    {
        parent :: setUp();
        $this->roleManager = $this->client
            ->getContainer()
            ->get("claroline.security.role_manager");
    }
    
    public function testANewRoleCanbeCreatedAndRetrieved()
    {
        $role = new Role();
        $role->setName('ROLE_TEST');
        $this->roleManager->create($role);
        
        $retrievedRole = $this->roleManager->getRole('ROLE_TEST');
        
        $this->assertEquals($role, $retrievedRole);
    }
    
    public function testCreateARoleWithInvalidNameThrowsAnException()
    {
        $this->setExpectedException('Claroline\CoreBundle\Exception\SecurityException');
        
        $role = new Role();
        $role->setName('');
        
        $this->roleManager->create($role);
    }
    
    public function testARoleCantBeDuplicated()
    {
        $this->setExpectedException('Claroline\CoreBundle\Exception\SecurityException');
        
        $firstRole = new Role();
        $firstRole->setName('ROLE_TEST');
        $secondRole = new Role();
        $secondRole->setName('ROLE_TEST');
        
        $this->roleManager->create($firstRole);
        $this->roleManager->create($secondRole);
        
        $em = $this->client->getContainer()->get('doctrine')->getEntityManager();
        $roles = $em->getReposistory('Claroline\CoreBundle\Entity\Role')->findAll();        
        $this->assertEquals(1, count($roles));
    }
    
    public function testGetRoleDoesntCreateANonExistentRoleIfNotSpecified()
    {
        $role = $this->roleManager->getRole('ROLE_NON_EXISTANT');
        $this->assertNull($role);
    }
    
    public function testGetRoleCreatesANewRoleIfSpecifiedAnIfRoleDoesntExist()
    {
        $role = $this->roleManager->getRole(
            'ROLE_NON_EXISTANT', 
            RoleManager::CREATE_IF_NOT_EXISTS
        );
        
        $this->assertInstanceOf('Claroline\CoreBundle\Entity\Role', $role);
        $this->assertEquals('ROLE_NON_EXISTANT', $role->getName());
        $this->assertEquals('ROLE_NON_EXISTANT', $role->getRole());
    }
}
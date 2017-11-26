<?php

namespace Civi\Test\Api4\Entity;

use Civi\Api4\Entity\Entity;
use Civi\Test\Api4\UnitTestCase;

/**
 * @group headless
 */
class EntityTest extends UnitTestCase  {

  public function testEntityGet() {
    $result = Entity::get()
      ->setCheckPermissions(FALSE)
      ->execute();
    $this->assertContains('Entity', $result,
      "Entity::get missing itself");
    $this->assertContains('Participant', $result,
      "Entity::get missing Participant");
  }

  public function testEntity() {
    $result = Entity::getActions()
      ->setCheckPermissions(FALSE)
      ->execute()
      ->indexBy('name');
    $this->assertEquals(
      array('get', 'getActions'),
      array_keys((array)$result),
      "Entity entity has more that basic actions");
  }

}
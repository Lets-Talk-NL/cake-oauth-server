<?php

namespace OAuthServer\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;
use DateInterval;
use InvalidArgumentException;
use OAuthServer\Lib\Enum\Repository;
use OAuthServer\Lib\Enum\Token;
use OAuthServer\Lib\Factory;
use stdClass;

class FactoryTest extends TestCase
{
    public function testClientId(): void
    {
        $clientId = Factory::clientId();
        $this->assertInternalType('string', $clientId);
        $this->assertEquals(20, strlen($clientId));
    }

    public function testClientSecret(): void
    {
        $clientSecret = Factory::clientSecret();
        $this->assertInternalType('string', $clientSecret);
        $this->assertEquals(40, strlen($clientSecret));
    }

    public function testDateInterval(): void
    {
        $dateInterval = Factory::dateInterval('P1M');
        $this->assertInstanceOf(DateInterval::class, $dateInterval);
        $dateInterval = Factory::dateInterval($dateInterval);
        $this->assertInstanceOf(DateInterval::class, $dateInterval);
        $this->expectException(InvalidArgumentException::class);
        Factory::dateInterval(new stdClass());
        $this->expectException(InvalidArgumentException::class);
        Factory::dateInterval('NOTADURATIONSTRING');
    }

    public function testTimeToLiveIntervals(): void
    {
        $intervals = Factory::timeToLiveIntervals([Token::ACCESS_TOKEN => 'P1M']);
        $this->assertInternalType('array', $intervals);
        $this->assertArrayHasKey(Token::ACCESS_TOKEN, $intervals);
        $this->assertInstanceOf(DateInterval::class, $intervals[Token::ACCESS_TOKEN]);
    }

    public function testIntervalTimestamp(): void
    {
        $timestamp = Factory::intervalTimestamp(new DateInterval('PT1S'));
        $this->assertEquals(1, $timestamp);
    }

    public function testCompleteRepositoryMappingDefaults(): void
    {
        $repositories = Factory::completeRepositoryMapping([]);
        $this->assertInternalType('array', $repositories);
        foreach (Repository::toArray() as $className) {
            $this->assertArrayHasKey($className, $repositories);
            $this->assertEquals($repositories[$className], Repository::aliasDefaults($className));
        }
    }

    public function testCompleteRepositoryMappingCustomMappingInput(): void
    {
        $repositories = Factory::completeRepositoryMapping([Repository::ACCESS_TOKEN => 'AliasForNonExistingTableToTest']);
        $this->assertInternalType('array', $repositories);
        $this->assertArrayHasKey(Repository::ACCESS_TOKEN, $repositories);
        $this->assertEquals($repositories[Repository::ACCESS_TOKEN], 'AliasForNonExistingTableToTest');
        $defaults = Repository::aliasDefaults();
        unset($defaults[Repository::ACCESS_TOKEN]);
        foreach ($defaults as $className => $alias) {
            $this->assertArrayHasKey($className, $repositories);
            $this->assertEquals($repositories[$className], $alias);
        }
    }
}

<?php

namespace Tests\Unit;

use App\Data\SignUpUserData;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery\MockInterface;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A unique violation at insert time means someone else took a value between the existence checks and
 * the insert. Only a taken email may be answered as "already registered"; anything else must surface.
 */
class UserServiceSignUpTest extends TestCase
{

    /**
     * A sign-up for a new address.
     *
     * @return SignUpUserData
     */
    private function signUpData(): SignUpUserData
    {
        return new SignUpUserData(
            firstName: 'Ada',
            lastName: 'Lovelace',
            email: 'ada@race.test',
            phoneNumber: '5550100',
            password: 'password'
        );
    }

    /**
     * A repository whose insert collides, and whose email check answers the given values in turn.
     *
     * @param bool ...$emailExistsAnswers
     *
     * @return void
     */
    private function collidingRepository(bool ...$emailExistsAnswers): void
    {
        $this->mock(UserRepository::class, function (MockInterface $repository) use ($emailExistsAnswers): void {
            $repository->shouldReceive('isEmailExists')->andReturn(...$emailExistsAnswers);
            $repository->shouldReceive('isUsernameExists')->andReturn(false);
            $repository->shouldReceive('create')->andThrow(
                new UniqueConstraintViolationException('pgsql', 'insert into "users"', [], new PDOException('duplicate key'))
            );
        });
    }

    #[Test]
    public function anEmailTakenByAConcurrentSignUpGetsTheGenericAnswer(): void
    {
        // Free when checked, taken by the time of the insert.
        $this->collidingRepository(false, true);

        self::assertNull(app(UserService::class)->signUp($this->signUpData()));
    }

    #[Test]
    public function anyOtherUniqueViolationSurfaces(): void
    {
        $this->collidingRepository(false, false);

        $this->expectException(UniqueConstraintViolationException::class);

        app(UserService::class)->signUp($this->signUpData());
    }

}

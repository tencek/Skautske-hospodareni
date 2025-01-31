<?php

declare(strict_types=1);

namespace Model\Bank\Fio;

use Cake\Chronos\Date;
use Codeception\Test\Unit;
use FioApi\Downloader;
use FioApi\Exceptions\InternalErrorException;
use FioApi\Exceptions\TooGreedyException;
use GuzzleHttp\Exception\TransferException;
use Mockery as m;
use Model\BankTimeLimit;
use Model\BankTimeout;
use Model\Payment\BankAccount;
use Model\Payment\TokenNotSet;
use Psr\Log\NullLogger;

class FioClientTest extends Unit
{
    public function testBankAccountWithoutTokenThrowsException(): void
    {
        $factory = m::mock(IDownloaderFactory::class);
        $fio     = new FioClient($factory, new NullLogger());

        $this->expectException(TokenNotSet::class);

        $fio->getTransactions(
            Date::today(),
            Date::today(),
            $this->mockAccount(null),
        );
    }

    public function testTooGreedyExceptionResultsInLimitException(): void
    {
        $since = Date::today();
        $until = Date::today();

        $downloader = m::mock(Downloader::class);
        $downloader->shouldReceive('downloadFromTo')
            ->with($since, $until)
            ->once()
            ->andThrow(TooGreedyException::class);

        $factory = $this->buildDownloaderFactory($downloader);
        $fio     = new FioClient($factory, new NullLogger());

        $this->expectException(BankTimeLimit::class);

        $fio->getTransactions($since, $until, $this->mockAccount());
    }

    public function tesGeneralApiErrorExceptionResultsInTimeoutException(): void
    {
        $since = Date::today();
        $until = Date::today();

        $downloader = m::mock(Downloader::class);
        $downloader->shouldReceive('downloadFromTo')
            ->with($since, $until)
            ->once()
            ->andThrow(TransferException::class);

        $factory = $this->buildDownloaderFactory($downloader);
        $fio     = new FioClient($factory, new NullLogger());

        $this->expectException(BankTimeout::class);

        $fio->getTransactions($since, $until, $this->mockAccount());
    }

    public function testInternalErrorResultsInTimeoutException(): void
    {
        $since = Date::today();
        $until = Date::today();

        $downloader = m::mock(Downloader::class);
        $downloader->shouldReceive('downloadFromTo')
            ->with($since, $until)
            ->once()
            ->andThrow(InternalErrorException::class);

        $factory = $this->buildDownloaderFactory($downloader);
        $fio     = new FioClient($factory, new NullLogger());

        $this->expectException(BankTimeout::class);

        $fio->getTransactions($since, $until, $this->mockAccount());
    }

    private function mockAccount(?string $token = 'token'): BankAccount
    {
        return m::mock(BankAccount::class, [
            'getId' => 10,
            'getToken' => $token,
        ]);
    }

    private function buildDownloaderFactory(Downloader $downloader): IDownloaderFactory
    {
        $factory = m::mock(IDownloaderFactory::class);
        $factory->shouldReceive('create')
            ->once()
            ->with('token')
            ->andReturn($downloader);

        return $factory;
    }
}

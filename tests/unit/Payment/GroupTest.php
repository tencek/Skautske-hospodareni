<?php

namespace Model\Payment;

use DateTimeImmutable;
use Model\Payment\EmailTemplate;
use Mockery as m;
use Model\Payment\Group\PaymentDefaults;

class GroupTest extends \Codeception\Test\Unit
{

    public function testCreate()
    {
        $dueDate = new DateTimeImmutable();
        $createdAt = new DateTimeImmutable();
        $variableSymbol = new VariableSymbol('666');
        $paymentDefaults = new PaymentDefaults(200.2, $dueDate, 203, $variableSymbol);
        $bankAccount = m::mock(BankAccount::class, ['getId' => 23, 'getUnitId' => 20]);
        $emails = \Helpers::createEmails();

        $group = new Group(20, NULL, "Skupina 01", $paymentDefaults, $createdAt, $emails, NULL, $bankAccount);

        $this->assertSame(20, $group->getUnitId());
        $this->assertNull($group->getObject());
        $this->assertSame("Skupina 01", $group->getName());
        $this->assertSame(200.2, $group->getDefaultAmount());
        $this->assertSame($dueDate, $group->getDueDate());
        $this->assertSame(203, $group->getConstantSymbol());
        $this->assertSame($variableSymbol, $group->getNextVariableSymbol());
        $this->assertSame($createdAt, $group->getCreatedAt());
        $this->assertEmailsAreSame($emails, $group->getEmailTemplates());
        $this->assertNull($group->getSmtpId());
        $this->assertSame($group::STATE_OPEN, $group->getState());
        $this->assertSame("", $group->getNote());
        $this->assertSame(23, $group->getBankAccountId());
    }

    public function testCreatingGroupWithNotAllEmailsThrowsException(): void
    {
        $paymentDefaults = new PaymentDefaults(NULL, NULL, NULL, NULL);
        $emails = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Email templates (" . EmailType::PAYMENT_INFO . ") are missing");

        $group = new Group(1, NULL, 'Test', $paymentDefaults, new DateTimeImmutable(), $emails, NULL, NULL);
    }

    public function testUpdate()
    {
        $dueDate = new DateTimeImmutable();
        $createdAt = new DateTimeImmutable();
        $group = $this->createGroup($dueDate, $createdAt);

        $emails = [
            EmailType::PAYMENT_INFO => new EmailTemplate("subject2", "body2"),
        ];

        $bankAccount = m::mock(BankAccount::class, ['getId' => 33, 'getUnitId' => 20]);

        $group->update("Skupina Jiná", new PaymentDefaults(120, NULL, NULL, NULL), $emails, 20, $bankAccount);

        $this->assertSame(20, $group->getUnitId());
        $this->assertNull($group->getObject());
        $this->assertSame("Skupina Jiná", $group->getName());
        $this->assertSame(120.0, $group->getDefaultAmount());
        $this->assertNull($group->getDueDate());
        $this->assertNull($group->getConstantSymbol());
        $this->assertNull($group->getNextVariableSymbol());
        $this->assertSame($createdAt, $group->getCreatedAt());
        $this->assertEmailsAreSame($emails, $group->getEmailTemplates());
        $this->assertSame(20, $group->getSmtpId());
        $this->assertSame(33, $group->getBankAccountId());
    }

    public function testUpdateWithNotAllEmailsThrowsException()
    {
        $group = $this->createGroup();
        $emails = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Email templates (" . EmailType::PAYMENT_INFO . ") are missing");

        $group->update('name', $group->getPaymentDefaults(), $emails, NULL, NULL);
    }

    public function testClose()
    {
        $group = $this->createGroup();
        $note = "Closed because of ...";

        $group->close($note);

        $this->assertSame(Group::STATE_CLOSED, $group->getState());
        $this->assertSame($note, $group->getNote());
    }

    public function testReopen()
    {
        $group = $this->createGroup();
        $group->close("Closed because of ...");
        $note = "Reopend because someone didn't pay!";

        $group->open($note);

        $this->assertSame(Group::STATE_OPEN, $group->getState());
        $this->assertSame($note, $group->getNote());
    }

    public function testRemoveBankAccount()
    {
        $group = $this->createGroup(
            NULL,
            NULL,
            m::mock(BankAccount::class, ['getId' => 10, 'getUnitId' => 20])
        );

        $group->removeBankAccount();

        $this->assertNull($group->getBankAccountId());
    }

    private function createGroup(?DateTimeImmutable $dueDate = NULL, ?DateTimeImmutable $createdAt = NULL, BankAccount $bankAccount = NULL): Group
    {
        $paymentDefaults = new PaymentDefaults(200.2, $dueDate ?? new DateTimeImmutable(), 203, new VariableSymbol('666'));
        $createdAt = $createdAt ?? new DateTimeImmutable();
        $emails = \Helpers::createEmails();

        return new Group(20, NULL, "Skupina 01", $paymentDefaults, $createdAt, $emails, NULL, $bankAccount);
    }

    /**
     * @param EmailTemplate[] $expected
     * @param EmailTemplate[] $actual
     */
    private function assertEmailsAreSame(array $expected, array $actual): void
    {
        $this->assertCount(count($expected), $actual);
        foreach($expected as $key => $value) {
            $this->assertTrue($value->equals($actual[$key]));
        }
    }

}

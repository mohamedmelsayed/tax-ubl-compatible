<?php
namespace Tests\Readers;

use Einvoicing\Readers\UblReader;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use function file_get_contents;

final class UblReaderTest extends TestCase {
    const DOCUMENT_PATH = __DIR__ . "/peppol-example.xml";

    /** @var UblReader */
    private $reader;

    protected function setUp(): void {
        $this->reader = new UblReader();
    }

    public function testCanReadInvoice(): void {
        $invoice = $this->reader->import(file_get_contents(self::DOCUMENT_PATH));
        $invoice->validate();

        $lines = $invoice->getLines();
        $this->assertEquals('1', $lines[0]->getId());
        $this->assertEquals('2', $lines[1]->getId());

        $totals = $invoice->getTotals();
        $this->assertEquals(1300, $totals->netAmount);
        $this->assertEquals(1325, $totals->taxExclusiveAmount);
        $this->assertEquals(331.25, $totals->vatAmount);
        $this->assertEquals(0, $totals->allowancesAmount);
        $this->assertEquals(25, $totals->chargesAmount);
        $this->assertEquals(1656.25, $totals->payableAmount);
        $this->assertEquals('S', $totals->vatBreakdown[0]->category);
        $this->assertEquals(25, $totals->vatBreakdown[0]->rate);
        $this->assertEquals('INV-123', $invoice->getPrecedingInvoiceReferences()[0]->getValue());
        $this->assertEquals('This is a sample string', $invoice->getAttachments()[0]->getContents());
    }

    public function testCannotReadInvoiceFromInvalidXml(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->reader->import(
            '<Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
                xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
                xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
                <cbc:CustomizationID>0.0.1</cbc:CustomizationID>
                <cbc:ProfileID>Not-a-EN-16931-Invoice</cbc:ProfileID>
                <cac:TaxTotal>
                    <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
                    <cac:TaxSubtotal>
                        <cbc:TaxableAmount currencyID="EUR">100.00</cbc:TaxableAmount>
                        <cbc:TaxAmount currencyID="EUR">0.00</cbc:TaxAmount>
                        <cbc:Percent>0.00</cbc:Percent>
                        <cac:TaxCategory>
                            <cbc:TaxExemptionReasonCode>No tax</cbc:TaxExemptionReasonCode>
                        </cac:TaxCategory>
                    </cac:TaxSubtotal>
                </cac:TaxTotal>
             </Invoice>'
        );
    }
}

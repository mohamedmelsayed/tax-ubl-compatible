<?php
namespace App\Http\Services;
use Illuminate\Support\Str;

use DateTime;
use Einvoicing\Identifier;
use Einvoicing\Invoice;
use Einvoicing\InvoiceLine;
use Einvoicing\Party;
use Einvoicing\Writers\UblWriter;
use Ramsey\Uuid\Uuid;

class InvoiceToUbl
{
    public function xml($invoice)
    {
        # code...
        
    }
    public function createXml($invoice)
    {
        // Create PEPPOL invoice instance
        $inv = new Invoice(Presets\Peppol::class);
        $inv
            ->setType('01000001')
            ->setNumber('03034092')
            ->setIssueDate(new DateTime('2020-11-01'))
            ->setUuid(Uuid::uuid6())
            ->setVatCurrency('SAR')
            ->setCurrency('SAR')
            ->setDueDate(new DateTime('2020-11-30'));

        // Set seller
        $seller = new Party();
        $seller
            ->setElectronicAddress(
                new Identifier('9482348239847239874', '0088')
            )
            ->setCompanyId(new Identifier('AH88726', '0183'))
            ->setName('Seller Name Ltd.')
            ->setTradingName('Seller Name')
            ->setVatNumber('ESA00000000')
            ->setAddress(['2001', '3456'])
            ->setCity('Springfield') 
            ->setBuildingNumber('3234')
            ->setTradingName('Seller Name')
            ->setVatNumber('300010000200023')
            ->setPostalCode('20051')
            ->setSubdivision('dfsdfsdf')
   
            ->setCountry('DE');
        $inv->setSeller($seller);

        // Set buyer
        $buyer = new Party();
        $buyer
            ->setElectronicAddress(new Identifier('ES12345', '0002'))
            ->setName('Buyer Name Ltd.')
            ->setCountry('FR');
        $inv->setBuyer($buyer);

        $inv->setBusinessProcess("reporting:1.0");

        // Add a product line
        $line = new InvoiceLine();
        $line
            ->setName('Product Name')
            ->setPrice(100)
            ->setVatRate(16)
            ->setQuantity(1);
        $inv->addLine($line);

        // Export invoice to a UBL document
        header('Content-Type: text/xml');
        $writer = new UblWriter();
        return $writer->export($inv);
    }
}

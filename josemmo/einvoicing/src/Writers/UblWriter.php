<?php
namespace Einvoicing\Writers;

use Einvoicing\AllowanceOrCharge;
use Einvoicing\Attachment;
use Einvoicing\Delivery;
use Einvoicing\Identifier;
use Einvoicing\Invoice;
use Einvoicing\InvoiceLine;
use Einvoicing\Models\InvoiceTotals;
use Einvoicing\Party;
use Einvoicing\Payments\Card;
use Einvoicing\Payments\Mandate;
use Einvoicing\Payments\Payment;
use Einvoicing\Payments\Transfer;
use UXML\UXML;
use function in_array;

class UblWriter extends AbstractWriter {
    const NS_INVOICE = "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2";
    const NS_CAC = "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2";
    const NS_CBC = "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2";
    const NS_EXT = "urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2";
    const NS_SIG="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2";
    const NS_SAC="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2";
    const NS_SBC="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2";
    const NS_DS="http://www.w3.org/2000/09/xmldsig#";

    /**
     * @inheritdoc
     */
    public function export(Invoice $invoice): string {
        $key="MIID6TCCA5CgAwIBAgITbwAAf8tem6jngr16DwABAAB/yzAKBggqhkjOPQQDAjBjMRUwEwYKCZImiZPyLGQBGRYFbG9jYWwxEzARBgoJkiaJk/IsZAEZFgNnb3YxFzAVBgoJkiaJk/IsZAEZFgdleHRnYXp0MRwwGgYDVQQDExNUU1pFSU5WT0lDRS1TdWJDQS0xMB4XDTIyMDkxNDEzMjYwNFoXDTI0MDkxMzEzMjYwNFowTjELMAkGA1UEBhMCU0ExEzARBgNVBAoTCjMxMTExMTExMTExDDAKBgNVBAsTA1RTVDEcMBoGA1UEAxMTVFNULTMxMTExMTExMTEwMTExMzBWMBAGByqGSM49AgEGBSuBBAAKA0IABGGDDKDmhWAITDv7LXqLX2cmr6+qddUkpcLCvWs5rC2O29W/hS4ajAK4Qdnahym6MaijX75Cg3j4aao7ouYXJ9GjggI5MIICNTCBmgYDVR0RBIGSMIGPpIGMMIGJMTswOQYDVQQEDDIxLVRTVHwyLVRTVHwzLWE4NjZiMTQyLWFjOWMtNDI0MS1iZjhlLTdmNzg3YTI2MmNlMjEfMB0GCgmSJomT8ixkAQEMDzMxMTExMTExMTEwMTExMzENMAsGA1UEDAwEMTEwMDEMMAoGA1UEGgwDVFNUMQwwCgYDVQQPDANUU1QwHQYDVR0OBBYEFDuWYlOzWpFN3no1WtyNktQdrA8JMB8GA1UdIwQYMBaAFHZgjPsGoKxnVzWdz5qspyuZNbUvME4GA1UdHwRHMEUwQ6BBoD+GPWh0dHA6Ly90c3RjcmwuemF0Y2EuZ292LnNhL0NlcnRFbnJvbGwvVFNaRUlOVk9JQ0UtU3ViQ0EtMS5jcmwwga0GCCsGAQUFBwEBBIGgMIGdMG4GCCsGAQUFBzABhmJodHRwOi8vdHN0Y3JsLnphdGNhLmdvdi5zYS9DZXJ0RW5yb2xsL1RTWkVpbnZvaWNlU0NBMS5leHRnYXp0Lmdvdi5sb2NhbF9UU1pFSU5WT0lDRS1TdWJDQS0xKDEpLmNydDArBggrBgEFBQcwAYYfaHR0cDovL3RzdGNybC56YXRjYS5nb3Yuc2Evb2NzcDAOBgNVHQ8BAf8EBAMCB4AwHQYDVR0lBBYwFAYIKwYBBQUHAwIGCCsGAQUFBwMDMCcGCSsGAQQBgjcVCgQaMBgwCgYIKwYBBQUHAwIwCgYIKwYBBQUHAwMwCgYIKoZIzj0EAwIDRwAwRAIgOgjNPJW017lsIijmVQVkP7GzFO2KQKd9GHaukLgIWFsCIFJF9uwKhTMxDjWbN+1awsnFI7RLBRxA/6hZ+F1wtaqU";
        $totals = $invoice->getTotals(false);
        $xml = UXML::newInstance('Invoice', null, [
            'xmlns' => self::NS_INVOICE,
            'xmlns:cac' => self::NS_CAC,
            'xmlns:cbc' => self::NS_CBC,
            'xmlns:ext' => self::NS_EXT,
        
            
        ]);

        // BT-24: Specification identifier
        $specificationIdentifier = $invoice->getSpecification();
        
        if ($specificationIdentifier !== null) {
           $ublExtension= $xml->add('ext:UBLExtensions')->add('ext:UBLExtension');
           $ublExtension->add('ext:ExtensionURI',"urn:oasis:names:specification:ubl:dsig:enveloped:xades");
           $extContent=$ublExtension->add('ext:ExtensionContent');
           $ublSignature=$extContent->add('sig:UBLDocumentSignatures',null,[
            'xmlns:sig' => self::NS_SIG,
            'xmlns:sac' => self::NS_SAC,
            'xmlns:sbc' => self::NS_SBC,
            'xmlns:ds' => self::NS_DS,
           ]);
           $singatureInformation=$ublSignature->add('sac:SignatureInformation');
           $singatureInformation->add('cbc:ID','urn:oasis:names:specification:ubl:signature:1');
           $singatureInformation->add('sbc:ReferencedSignatureID','urn:oasis:names:specification:ubl:signature:Invoice');
           $singature=$singatureInformation->add('ds:Signature',null,[ 'xmlns:ds'=>"http://www.w3.org/2000/09/xmldsig#",'id'=>'signature']);
            $singatureInfo=$singature->add('ds:SignedInfo');
            $reference=$singatureInfo->add('ds:Reference',null,["id"=>"invoiceSignedData","url"=>""]);
            $reference->add('ds:DigestMethod',null,["Algorithm"=>"http://www.w3.org/2001/04/xmlenc#sha256"]);
            $reference->add('ds:DigestValue',"WI6GNwty4XrTc3P1WrRM1xlhqz9TimXdCLH9sgmj0Sg=");
            $transforms=$reference->add('ds:Transforms');
            $transforms->add('ds:Transform',null,["Algorithm"=>"http://www.w3.org/TR/1999/REC-xpath-19991116"])->add('ds:XPath','not(//ancestor-or-self::ext:UBLExtensions)');
            $transforms->add('ds:Transform',null,["Algorithm"=>"http://www.w3.org/TR/1999/REC-xpath-19991116"])->add('ds:XPath','not(//ancestor-or-self::cac:Signature)');
            $transforms->add('ds:Transform',null,["Algorithm"=>"http://www.w3.org/TR/1999/REC-xpath-19991116"])->add('ds:XPath',"not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])");
                $transforms->add('ds:Transform',null,["Algorithm"=>"http://www.w3.org/2006/12/xml-c14n11"]);
                    $reference2=$singatureInfo->add('ds:Reference',null,["TYPE"=>"http://www.w3.org/2000/09/xmldsig#SignatureProperties","url"=>"#xadesSignedProperties"]);
            $reference2->add('ds:DigestMethod',null,["Algorithm"=>"http://www.w3.org/2001/04/xmlenc#sha256"]);
            $reference->add('ds:DigestValue',"ZjU2ZjM4YTExODRmNzE0ZjIxODA4MDYxYjhiMzdmM2JlMTJiNWQ0N2E2YjhjNzQwMjg2NDBkMzJlM2MxNjM2Nw==");

            $singatureInfo->add('ds:CanonicalizationMethod',null,["Algorithm"=>"http://www.w3.org/2006/12/xml-c14n11"]);
            $singatureInfo->add('ds:SignatureMethod',null,["Algorithm"=>"http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"]);
            $singature->add('ds:SignatureValue',"MEUCIQCCGL7AJacVObs7luFYTbsqKr9qLZX+LYjZivOjDNnaYgIgT0SrZZKk3L8fzV8/J7h9p7wH0BoqplW0RBcWOVNeW0w=");
            $singature->add('ds:KeyInfo')->add('ds:X509Data')->add('ds:X509Certificate',$key);
            $signedSignatureProperties=$singature->add('ds:Object')->add('xades:QualifyingProperties',null,["xmlns:xades"=>"http://uri.etsi.org/01903/v1.3.2#","Target"=>"signature"])->add('xades:SignedProperties',null,['id'=>'xadesSignedProperties'])->add('xades:SignedSignatureProperties');
            $signedSignatureProperties->add('xades:SigningTime','2022-09-15T01:45:47Z');
            $cert=$signedSignatureProperties->add('xades:SigningCertificate')->add('xades:Cert');
            $certDigest=$cert->add('xades:CertDigest');
            $certDigest->add('ds:DigestMethod',null,["Algorithm"=>"http://www.w3.org/2001/04/xmlenc#sha256"]);
            $certDigest->add('ds:DigestValue','YTJkM2JhYTcwZTBhZTAxOGYwODMyNzY3NTdkZDM3YzhjY2IxOTIyZDZhM2RlZGJiMGY0NDUzZWJhYWI4MDhmYg==');
            $issuerSerial=$cert->add('xades:IssuerSerial');
            $issuerSerial->add('ds:X509IssuerName','CN=TSZEINVOICE-SubCA-1, DC=extgazt, DC=gov, DC=local');
            $issuerSerial->add('ds:X509SerialNumber','2475382886904809774818644480820936050208702411');
       
       
        }

        // BT-23: Business process type
        $businessProcessType = $invoice->getBusinessProcess();
        if ($businessProcessType !== null) {
            $xml->add('cbc:ProfileID', $businessProcessType);
        }

        // BT-1: Invoice number
        $number = $invoice->getNumber();
        if ($number !== null) {
            $xml->add('cbc:ID', $number);
        }

        $uuid = $invoice->getUuid();
        if ($uuid !== null) {
            $xml->add('cbc:UUID', $uuid);
        }

        // BT-2: Issue date
        $issueDate = $invoice->getIssueDate();
        if ($issueDate !== null) {
            $xml->add('cbc:IssueDate', $issueDate->format('Y-m-d'));
        }

        // BT-9: Due date
        $dueDate = $invoice->getDueDate();
        if ($dueDate !== null) {
            $xml->add('cbc:DueDate', $dueDate->format('Y-m-d'));
        }

        // BT-3: Invoice type code
        $xml->add('cbc:InvoiceTypeCode', (string) $invoice->getType(),['name' => $invoice->getType()]);

        // BT-22: Notes
        foreach ($invoice->getNotes() as $note) {
            $xml->add('cbc:Note', $note);
        }

        // BT-7: Tax point date
        $taxPointDate = $invoice->getTaxPointDate();
        if ($taxPointDate !== null) {
            $xml->add('cbc:TaxPointDate', $taxPointDate->format('Y-m-d'));
        }

        // BT-5: Invoice currency code
        $xml->add('cbc:DocumentCurrencyCode', $invoice->getCurrency());

        // BT-6: VAT accounting currency code
        $vatCurrency = $invoice->getVatCurrency();
        if ($vatCurrency !== null) {
            $xml->add('cbc:TaxCurrencyCode', $vatCurrency);
        }

        // BT-19: Buyer accounting reference
        $buyerAccountingReference = $invoice->getBuyerAccountingReference();
        if ($buyerAccountingReference !== null) {
            $xml->add('cbc:AccountingCost', $buyerAccountingReference);
        }

        // BT-10: Buyer reference
        $buyerReference = $invoice->getBuyerReference();
        if ($buyerReference !== null) {
            $xml->add('cbc:BuyerReference', $buyerReference);
        }

        // BG-14: Invoice period
        $this->addPeriodNode($xml, $invoice);

        // Order reference node
        $this->addOrderReferenceNode($xml, $invoice);

        // BG-3: Preceding invoice reference
        foreach ($invoice->getPrecedingInvoiceReferences() as $invoiceReference) {
            $invoiceDocumentReferenceNode = $xml->add('cac:BillingReference')->add('cac:InvoiceDocumentReference');
            $invoiceDocumentReferenceNode->add('cbc:ID', $invoiceReference->getValue());
            $invoiceReferenceIssueDate = $invoiceReference->getIssueDate();
            if ($invoiceReferenceIssueDate !== null) {
                $invoiceDocumentReferenceNode->add('cbc:IssueDate', $invoiceReferenceIssueDate->format('Y-m-d'));
            }
        }

        // BT-17: Tender or lot reference
        $tenderOrLotReference = $invoice->getTenderOrLotReference();
        if ($tenderOrLotReference !== null) {
            $xml->add('cac:OriginatorDocumentReference')->add('cbc:ID', $tenderOrLotReference);
        }

        // BT-12: Contract reference
        $contractReference = $invoice->getContractReference();
        if ($contractReference !== null) {
            $xml->add('cac:ContractDocumentReference')->add('cbc:ID', $contractReference);
        }

        // BG-24: Attachments node
        foreach ($invoice->getAttachments() as $attachment) {
            $this->addAttachmentNode($xml, $attachment);
        }

        // Seller node
        $seller = $invoice->getSeller();
        if ($seller !== null) {
            $this->addSellerOrBuyerNode($xml->add('cac:AccountingSupplierParty'), $seller);
        }

        // Buyer node
        $buyer = $invoice->getBuyer();
        if ($buyer !== null) {
            $this->addSellerOrBuyerNode($xml->add('cac:AccountingCustomerParty'), $buyer);
        }

        // Payee node
        $payee = $invoice->getPayee();
        if ($payee !== null) {
            $this->addPayeeNode($xml, $payee);
        }

        // Delivery node
        $delivery = $invoice->getDelivery();
        if ($delivery !== null) {
            $this->addDeliveryNode($xml, $delivery);
        }

        // Payment nodes
        $payment = $invoice->getPayment();
        if ($payment !== null) {
            $this->addPaymentNodes($xml, $payment);
        }

        // Allowances and charges
        foreach ($invoice->getAllowances() as $item) {
            $this->addAllowanceOrCharge($xml, $item, false, $invoice, $totals, null);
        }
        foreach ($invoice->getCharges() as $item) {
            $this->addAllowanceOrCharge($xml, $item, true, $invoice, $totals, null);
        }

        // Invoice totals
        $this->addTaxTotalNodes($xml, $invoice, $totals);
        $this->addDocumentTotalsNode($xml, $invoice, $totals);

        // Invoice lines
        $lines = $invoice->getLines();
        $lastGenId = 0;
        $usedIds = [];
        foreach ($lines as $line) {
            $lineId = $line->getId();
            if ($lineId !== null) {
                $usedIds[] = $lineId;
            }
        }
        foreach ($lines as $line) {
            $this->addLineNode($xml, $line, $invoice, $lastGenId, $usedIds);
        }

        return $xml->asXML();
    }


    /**
     * Add identifier node
     * @param UXML       $parent     Parent element
     * @param string     $name       New node name
     * @param Identifier $identifier Identifier instance
     * @param string     $schemeAttr Scheme attribute name
     */
    private function addIdentifierNode(UXML $parent, string $name, Identifier $identifier, string $schemeAttr="schemeID") {
        $scheme = $identifier->getScheme();
        $attrs = ($scheme === null) ? [] : ["$schemeAttr" => $scheme];
        $parent->add($name, $identifier->getValue(), $attrs);
    }


    /**
     * Add period node
     * @param UXML                $parent Parent element
     * @param Invoice|InvoiceLine $source Source instance
     */
    private function addPeriodNode(UXML $parent, $source) {
        $startDate = $source->getPeriodStartDate();
        $endDate = $source->getPeriodEndDate();
        if ($startDate === null && $endDate === null) return;

        $xml = $parent->add('cac:InvoicePeriod');

        // Period start date
        if ($startDate !== null) {
            $xml->add('cbc:StartDate', $startDate->format('Y-m-d'));
        }

        // Period end date
        if ($endDate !== null) {
            $xml->add('cbc:EndDate', $endDate->format('Y-m-d'));
        }
    }


    /**
     * Add order reference node
     * @param UXML    $parent  Parent element
     * @param Invoice $invoice Invoice instance
     */
    private function addOrderReferenceNode(UXML $parent, Invoice $invoice) {
        $purchaseOrderReference = $invoice->getPurchaseOrderReference();
        $salesOrderReference = $invoice->getSalesOrderReference();
        if ($purchaseOrderReference === null && $salesOrderReference === null) return;

        $orderReferenceNode = $parent->add('cac:OrderReference');

        // BT-13: Purchase order reference
        if ($purchaseOrderReference !== null) {
            $orderReferenceNode->add('cbc:ID', $purchaseOrderReference);
        }

        // BT-14: Sales order reference
        if ($salesOrderReference !== null) {
            $orderReferenceNode->add('cbc:SalesOrderID', $salesOrderReference);
        }
    }


    /**
     * Add amount node
     * @param UXML   $parent   Parent element
     * @param string $name     New node name
     * @param float  $amount   Amount
     * @param string $currency Currency code
     */
    private function addAmountNode(UXML $parent, string $name, float $amount, string $currency) {
        $parent->add($name, (string) $amount, ['currencyID' => $currency]);
    }


    /**
     * Add VAT node
     * @param UXML        $parent              Parent element
     * @param string      $name                New node name
     * @param string      $category            VAT category
     * @param float|null  $rate                VAT rate
     * @param string|null $exemptionReasonCode VAT exemption reason code
     * @param string|null $exemptionReason     VAT exemption reason as text
     */
    private function addVatNode(
        UXML $parent, string $name, string $category, ?float $rate,
        ?string $exemptionReasonCode=null, ?string $exemptionReason=null
    ) {
        $xml = $parent->add($name);

        // VAT category
        $xml->add('cbc:ID', $category);

        // VAT rate
        if ($rate !== null) {
            $xml->add('cbc:Percent', (string) $rate);
        }

        // Exemption reason code
        if ($exemptionReasonCode !== null) {
            $xml->add('cbc:TaxExemptionReasonCode', $exemptionReasonCode);
        }

        // Exemption reason (as text)
        if ($exemptionReason !== null) {
            $xml->add('cbc:TaxExemptionReason', $exemptionReason);
        }

        // Tax scheme
        $xml->add('cac:TaxScheme')->add('cbc:ID', 'VAT');
    }


    /**
     * Add postal address node
     * @param  UXML           $parent Parent element
     * @param  string         $name   New node name
     * @param  Delivery|Party $source Source instance
     * @return UXML                   Postal address node
     */
    private function addPostalAddressNode(UXML $parent, string $name, $source) {
        $xml = $parent->add($name);

          // building number 
          $buildingNumber = $source->getBuildingNumber();
          if ($buildingNumber !== null) {
              $xml->add('cbc:BuildingNumber', $buildingNumber);
          }
  

        // Street name
        $addressLines = $source->getAddress();
        if (isset($addressLines[0])) {
            $xml->add('cbc:StreetName', $addressLines[0]);
        }

        // Additional street name
        if (isset($addressLines[1])) {
            $xml->add('cbc:AdditionalStreetName', $addressLines[1]);
        }

        // City name
        $cityName = $source->getCity();
        if ($cityName !== null) {
            $xml->add('cbc:CityName', $cityName);
        }

        // Postal code
        $postalCode = $source->getPostalCode();
        if ($postalCode !== null) {
            $xml->add('cbc:PostalZone', $postalCode);
        }

       
         // Subdivision
         $subEntity = $source->getSubdivision();
         if ($subEntity !== null) {
             $xml->add('cbc:CountrySubentity', $subEntity);
         }

        // Address line (third address line)
        if (isset($addressLines[2])) {
            $xml->add('cac:AddressLine')->add('cbc:Line', $addressLines[2]);
        }

        // Country
        $country = $source->getCountry();
        if ($country !== null) {
            $xml->add('cac:Country')->add('cbc:IdentificationCode', $country);
        }

        return $xml;
    }


    /**
     * Add seller or buyer node
     * @param UXML  $parent Invoice element
     * @param Party $party  Party instance
     */
    private function addSellerOrBuyerNode(UXML $parent, Party $party) {
        $xml = $parent->add('cac:Party');

        // Electronic address
        $electronicAddress = $party->getElectronicAddress();
        if ($electronicAddress !== null) {
            $this->addIdentifierNode($xml, 'cbc:EndpointID', $electronicAddress);
        }

        

        // Additional identifiers
        foreach ($party->getIdentifiers() as $identifier) {
            $identifierNode = $xml->add('cac:PartyIdentification');
            $this->addIdentifierNode($identifierNode, 'cbc:ID', $identifier);
        }

        // Trading name
        $tradingName = $party->getTradingName();
        if ($tradingName !== null) {
            $xml->add('cac:PartyName')->add('cbc:Name', $tradingName);
        }

        // Postal address node
        $this->addPostalAddressNode($xml, 'cac:PostalAddress', $party);

        // VAT number
        $vatNumber = $party->getVatNumber();
        if ($vatNumber !== null) {
            $taxNode = $xml->add('cac:PartyTaxScheme');
            $taxNode->add('cbc:CompanyID', $vatNumber);
            $taxNode->add('cac:TaxScheme')->add('cbc:ID', 'VAT');
        }

        // Tax registration identifier
        $taxRegistrationId = $party->getTaxRegistrationId();
        if ($taxRegistrationId !== null) {
            $taxRegistrationNode = $xml->add('cac:PartyTaxScheme');
            $taxRegistrationNode->add('cbc:CompanyID', $taxRegistrationId->getValue());

            $taxRegistrationSchemeNode = $taxRegistrationNode->add('cac:TaxScheme');
            $taxRegistrationScheme = $taxRegistrationId->getScheme();
            if ($taxRegistrationScheme !== null) {
                $taxRegistrationSchemeNode->add('cbc:ID', $taxRegistrationScheme);
            }
        }

        // Initial legal entity node
        $legalEntityNode = $xml->add('cac:PartyLegalEntity');

        // Legal name
        $legalName = $party->getName();
        if ($legalName !== null) {
            $legalEntityNode->add('cbc:RegistrationName', $legalName);
        }

        // Company ID
        $companyId = $party->getCompanyId();
        if ($companyId !== null) {
            $this->addIdentifierNode($legalEntityNode, 'cbc:CompanyID', $companyId);
        }

        // BT-33: Seller additional legal information
        $legalInformation = $party->getLegalInformation();
        if ($legalInformation !== null) {
            $legalEntityNode->add('cbc:CompanyLegalForm', $legalInformation);
        }

        // Contact point
        if ($party->hasContactInformation()) {
            $contactNode = $xml->add('cac:Contact');
            
            $contactName = $party->getContactName();
            if ($contactName !== null) {
                $contactNode->add('cbc:Name', $contactName);
            }

            $contactPhone = $party->getContactPhone();
            if ($contactPhone !== null) {
                $contactNode->add('cbc:Telephone', $contactPhone);
            }

            $contactEmail = $party->getContactEmail();
            if ($contactEmail !== null) {
                $contactNode->add('cbc:ElectronicMail', $contactEmail);
            }
        }
    }


    /**
     * Add payee node
     * @param UXML  $parent Invoice element
     * @param Party $party  Party instance
     */
    private function addPayeeNode(UXML $parent, Party $party) {
        $xml = $parent->add('cac:PayeeParty');

        // Additional identifiers
        foreach ($party->getIdentifiers() as $identifier) {
            $identifierNode = $xml->add('cac:PartyIdentification');
            $this->addIdentifierNode($identifierNode, 'cbc:ID', $identifier);
        }

        // Party name
        $name = $party->getName();
        if ($name !== null) {
            $xml->add('cac:PartyName')->add('cbc:Name', $name);
        }

        // Company ID
        $companyId = $party->getCompanyId();
        if ($companyId !== null) {
            $legalEntityNode = $xml->add('cac:PartyLegalEntity');
            $this->addIdentifierNode($legalEntityNode, 'cbc:CompanyID', $companyId);
        }
    }


    /**
     * Add delivery node
     * @param UXML     $parent   Invoice element
     * @param Delivery $delivery Delivery instance
     */
    private function addDeliveryNode(UXML $parent, Delivery $delivery) {
        $xml = $parent->add('cac:Delivery');

        // BT-72: Actual delivery date
        $date = $delivery->getDate();
        if ($date !== null) {
            $xml->add('cbc:ActualDeliveryDate', $date->format('Y-m-d'));
        }

        // Initial delivery location node
        $locationNode = $xml->add('cac:DeliveryLocation');

        // BT-71: Delivery location identifier
        $locationIdentifier = $delivery->getLocationIdentifier();
        if ($locationIdentifier !== null) {
            $this->addIdentifierNode($locationNode, 'cbc:ID', $locationIdentifier);
        }

        // Delivery postal address
        $addressNode = $this->addPostalAddressNode($locationNode, 'cac:Address', $delivery);
        if ($addressNode->isEmpty()) {
            $addressNode->remove();
        }

        // BT-70: Deliver name
        $name = $delivery->getName();
        if ($name !== null) {
            $xml->add('cac:DeliveryParty')->add('cac:PartyName')->add('cbc:Name', $name);
        }

        // Remove location node if empty
        if ($locationNode->isEmpty()) {
            $locationNode->remove();
        }
    }


    /**
     * Add payment nodes
     * @param UXML    $parent  Invoice element
     * @param Payment $payment Payment instance
     */
    private function addPaymentNodes(UXML $parent, Payment $payment) {
        $xml = $parent->add('cac:PaymentMeans');

        // BT-81: Payment means code
        // BT-82: Payment means name
        $meansCode = $payment->getMeansCode();
        if ($meansCode !== null) {
            $meansText = $payment->getMeansText();
            $attrs = ($meansText === null) ? [] : ['name' => $meansText];
            $xml->add('cbc:PaymentMeansCode', $meansCode, $attrs);
        }

        // BT-83: Payment ID
        $paymentId = $payment->getId();
        if ($paymentId !== null) {
            $xml->add('cbc:PaymentID', $paymentId);
        }

        // BG-18: Payment card
        $card = $payment->getCard();
        if ($card !== null) {
            $this->addPaymentCardNode($xml, $card);
        }

        // BG-17: Payment transfers
        foreach ($payment->getTransfers() as $transfer) {
            $this->addPaymentTransferNode($xml, $transfer);
        }

        // BG-19: Payment mandate
        $mandate = $payment->getMandate();
        if ($mandate !== null) {
            $this->addPaymentMandateNode($xml, $mandate);
        }

        // Remove PaymentMeans node if empty
        if ($xml->isEmpty()) {
            $xml->remove();
        }

        // BT-20: Payment terms
        $terms = $payment->getTerms();
        if ($terms !== null) {
            $parent->add('cac:PaymentTerms')->add('cbc:Note', $terms);
        }
    }


    /**
     * Add payment card node
     * @param UXML $parent PaymentMeans element
     * @param Card $card   Card instance
     */
    private function addPaymentCardNode(UXML $parent, Card $card) {
        $xml = $parent->add('cac:CardAccount');

        // BT-87: Card PAN
        $pan = $card->getPan();
        if ($pan !== null) {
            $xml->add('cbc:PrimaryAccountNumberID', $pan);
        }

        // Card network
        $network = $card->getNetwork();
        if ($network !== null) {
            $xml->add('cbc:NetworkID', $network);
        }

        // BT-88: Holder name
        $holder = $card->getHolder();
        if ($holder !== null) {
            $xml->add('cbc:HolderName', $holder);
        }
    }


    /**
     * Add payment transfer node
     * @param UXML     $parent   PaymentMeans element
     * @param Transfer $transfer Transfer instance
     */
    private function addPaymentTransferNode(UXML $parent, Transfer $transfer) {
        $xml = $parent->add('cac:PayeeFinancialAccount');

        // BT-84: Receiving account ID
        $accountId = $transfer->getAccountId();
        if ($accountId !== null) {
            $xml->add('cbc:ID', $accountId);
        }

        // BT-85: Receiving account name
        $accountName = $transfer->getAccountName();
        if ($accountName !== null) {
            $xml->add('cbc:Name', $accountName);
        }

        // BT-86: Service provider ID
        $provider = $transfer->getProvider();
        if ($provider !== null) {
            $xml->add('cac:FinancialInstitutionBranch')->add('cbc:ID', $provider);
        }
    }


    /**
     * Add payment mandate node
     * @param UXML    $parent  PaymentMeans element
     * @param Mandate $mandate Mandate instance
     */
    private function addPaymentMandateNode(UXML $parent, Mandate $mandate) {
        $xml = $parent->add('cac:PaymentMandate');

        // BT-89: Mandate reference
        $reference = $mandate->getReference();
        if ($reference !== null) {
            $xml->add('cbc:ID', $reference);
        }

        // BT-91: Debited account
        $account = $mandate->getAccount();
        if ($account !== null) {
            $xml->add('cac:PayerFinancialAccount')->add('cbc:ID', $account);
        }
    }


    /**
     * Add allowance or charge
     * @param UXML               $parent   Parent element
     * @param AllowanceOrCharge  $item     Allowance or charge instance
     * @param boolean            $isCharge Is charge (TRUE) or allowance (FALSE)
     * @param Invoice            $invoice  Invoice instance
     * @param InvoiceTotals|null $totals   Unrounded invoice totals or NULL in case at line level
     * @param InvoiceLine|null   $line     Invoice line or NULL in case of at document level
     */
    private function addAllowanceOrCharge(
        UXML $parent,
        AllowanceOrCharge $item,
        bool $isCharge,
        Invoice $invoice,
        ?InvoiceTotals $totals,
        ?InvoiceLine $line
    ) {
        $atDocumentLevel = ($line === null);
        $xml = $parent->add('cac:AllowanceCharge');

        // Charge indicator
        $xml->add('cbc:ChargeIndicator', $isCharge ? 'true' : 'false');

        // Reason code
        $reasonCode = $item->getReasonCode();
        if ($reasonCode !== null) {
            $xml->add('cbc:AllowanceChargeReasonCode', $reasonCode);
        }

        // Reason text
        $reasonText = $item->getReason();
        if ($reasonText !== null) {
            $xml->add('cbc:AllowanceChargeReason', $reasonText);
        }

        // Percentage
        if ($item->isPercentage()) {
            $xml->add('cbc:MultiplierFactorNumeric', (string) $item->getAmount());
        }

        // Amount
        $baseAmount = $atDocumentLevel ?
            $totals->netAmount :          // @phan-suppress-current-line PhanPossiblyUndeclaredProperty
            $line->getNetAmount() ?? 0.0; // @phan-suppress-current-line PhanPossiblyNonClassMethodCall
        $this->addAmountNode(
            $xml,
            'cbc:Amount',
            $invoice->round($item->getEffectiveAmount($baseAmount), 'line/allowanceChargeAmount'),
            $invoice->getCurrency()
        );

        // Base amount
        if ($item->isPercentage()) {
            $this->addAmountNode(
                $xml,
                'cbc:BaseAmount',
                $invoice->round($baseAmount, 'line/netAmount'),
                $invoice->getCurrency()
            );
        }

        // Tax category
        if ($atDocumentLevel) {
            $this->addVatNode($xml, 'cac:TaxCategory', $item->getVatCategory(), $item->getVatRate());
        }
    }


    /**
     * Add tax total nodes
     * @param UXML          $parent  Parent element
     * @param Invoice       $invoice Invoice instance
     * @param InvoiceTotals $totals  Unrounded invoice totals
     */
    private function addTaxTotalNodes(UXML $parent, Invoice $invoice, InvoiceTotals $totals) {
        $xml = $parent->add('cac:TaxTotal');

        // Add tax amount
        $this->addAmountNode(
            $xml,
            'cbc:TaxAmount',
            $invoice->round($totals->vatAmount, 'invoice/taxAmount'),
            $totals->currency
        );

        // Add each tax details
        foreach ($totals->vatBreakdown as $item) {
            $vatBreakdownNode = $xml->add('cac:TaxSubtotal');
            $this->addAmountNode(
                $vatBreakdownNode,
                'cbc:TaxableAmount',
                $invoice->round($item->taxableAmount, 'invoice/allowancesChargesAmount'),
                $totals->currency
            );
            $this->addAmountNode(
                $vatBreakdownNode,
                'cbc:TaxAmount',
                $invoice->round($item->taxAmount, 'invoice/taxAmount'),
                $totals->currency
            );
            $this->addVatNode(
                $vatBreakdownNode,
                'cac:TaxCategory',
                $item->category,
                $item->rate,
                $item->exemptionReasonCode,
                $item->exemptionReason
            );
        }

        // Add tax amount in VAT accounting currency (if any)
        $customVatAmount = $totals->customVatAmount;
        if ($customVatAmount !== null) {
            $this->addAmountNode(
                $parent->add('cac:TaxTotal'),
                'cbc:TaxAmount',
                $invoice->round($customVatAmount, 'invoice/taxAmount'),
                $totals->vatCurrency ?? $totals->currency
            );
        }
    }


    /**
     * Add document totals node
     * @param UXML          $parent  Parent element
     * @param Invoice       $invoice Invoice instance
     * @param InvoiceTotals $totals  Unrounded invoice totals
     */
    private function addDocumentTotalsNode(UXML $parent, Invoice $invoice, InvoiceTotals $totals) {
        $xml = $parent->add('cac:LegalMonetaryTotal');

        // Build totals matrix
        $totalsMatrix = [];
        $totalsMatrix['cbc:LineExtensionAmount'] = $invoice->round(
            $totals->netAmount,
            'invoice/netAmount'
        );
        $totalsMatrix['cbc:TaxExclusiveAmount'] = $invoice->round(
            $totals->taxExclusiveAmount,
            'invoice/taxExclusiveAmount'
        );
        $totalsMatrix['cbc:TaxInclusiveAmount'] = $invoice->round(
            $totals->taxInclusiveAmount,
            'invoice/taxInclusiveAmount'
        );
        if ($totals->allowancesAmount > 0) {
            $totalsMatrix['cbc:AllowanceTotalAmount'] = $invoice->round(
                $totals->allowancesAmount,
                'invoice/allowancesChargesAmount'
            );
        }
        if ($totals->chargesAmount > 0) {
            $totalsMatrix['cbc:ChargeTotalAmount'] = $invoice->round(
                $totals->chargesAmount,
                'invoice/allowancesChargesAmount'
            );
        }
        if ($totals->paidAmount > 0) {
            $totalsMatrix['cbc:PrepaidAmount'] = $invoice->round(
                $totals->paidAmount,
                'invoice/paidAmount'
            );
        }
        if ($totals->roundingAmount > 0) {
            $totalsMatrix['cbc:PayableRoundingAmount'] = $invoice->round(
                $totals->roundingAmount,
                'invoice/roundingAmount'
            );
        }
        $totalsMatrix['cbc:PayableAmount'] = $invoice->round(
            $totals->payableAmount,
            'invoice/payableAmount'
        );

        // Create and append XML nodes
        foreach ($totalsMatrix as $field=>$amount) {
            $this->addAmountNode($xml, $field, $amount, $totals->currency);
        }
    }


    /**
     * Add invoice line
     * @param UXML        $parent     Parent XML element
     * @param InvoiceLine $line       Invoice line
     * @param Invoice     $invoice    Invoice instance
     * @param int         &$lastGenId Last used auto-generated ID
     * @param string[]    &$usedIds   Used invoice line IDs
     */
    private function addLineNode(UXML $parent, InvoiceLine $line, Invoice $invoice, int &$lastGenId, array &$usedIds) {
        $xml = $parent->add('cac:InvoiceLine');

        // BT-126: Invoice line identifier
        $lineId = $line->getId();
        if ($lineId === null) {
            do {
                $lineId = (string) ++$lastGenId;
            } while (in_array($lineId, $usedIds));
        }
        $xml->add('cbc:ID', $lineId);

        // BT-127: Invoice line note
        $note = $line->getNote();
        if ($note !== null) {
            $xml->add('cbc:Note', $note);
        }

        // BT-129: Invoiced quantity
        $xml->add('cbc:InvoicedQuantity', (string) $line->getQuantity(), ['unitCode' => $line->getUnit()]);

        // BT-131: Line net amount
        $netAmount = $line->getNetAmount();
        if ($netAmount !== null) {
            $this->addAmountNode(
                $xml,
                'cbc:LineExtensionAmount',
                $invoice->round($netAmount, 'line/netAmount'),
                $invoice->getCurrency()
            );
        }

        // BT-133: Buyer accounting reference
        $buyerAccountingReference = $line->getBuyerAccountingReference();
        if ($buyerAccountingReference !== null) {
            $xml->add('cbc:AccountingCost', $buyerAccountingReference);
        }

        // BG-26: Invoice line period
        $this->addPeriodNode($xml, $line);

        // BT-132: Order line reference
        $orderLineReference = $line->getOrderLineReference();
        if ($orderLineReference !== null) {
            $xml->add('cac:OrderLineReference')->add('cbc:LineID', $orderLineReference);
        }

        // Allowances and charges
        foreach ($line->getAllowances() as $item) {
            $this->addAllowanceOrCharge($xml, $item, false, $invoice, null, $line);
        }
        foreach ($line->getCharges() as $item) {
            $this->addAllowanceOrCharge($xml, $item, true, $invoice, null, $line);
        }

        // Initial item node
        $itemNode = $xml->add('cac:Item');

        // BT-154: Item description
        $description = $line->getDescription();
        if ($description !== null) {
            $itemNode->add('cbc:Description', $description);
        }

        // BT-153: Item name
        $name = $line->getName();
        if ($name !== null) {
            $itemNode->add('cbc:Name', $name);
        }

        // BT-156: Buyer identifier
        $buyerIdentifier = $line->getBuyerIdentifier();
        if ($buyerIdentifier !== null) {
            $itemNode->add('cac:BuyersItemIdentification')->add('cbc:ID', $buyerIdentifier);
        }

        // BT-155: Seller identifier
        $sellerIdentifier = $line->getSellerIdentifier();
        if ($sellerIdentifier !== null) {
            $itemNode->add('cac:SellersItemIdentification')->add('cbc:ID', $sellerIdentifier);
        }

        // BT-157: Standard identifier
        $standardIdentifier = $line->getStandardIdentifier();
        if ($standardIdentifier !== null) {
            $this->addIdentifierNode($itemNode->add('cac:StandardItemIdentification'), 'cbc:ID', $standardIdentifier);
        }

        // BT-159: Item origin country
        $originCountry = $line->getOriginCountry();
        if ($originCountry !== null) {
            $itemNode->add('cac:OriginCountry')->add('cbc:IdentificationCode', $originCountry);
        }

        // BT-158: Item classification identifiers
        foreach ($line->getClassificationIdentifiers() as $identifier) {
            $classNode = $itemNode->add('cac:CommodityClassification');
            $this->addIdentifierNode($classNode, 'cbc:ItemClassificationCode', $identifier, 'listID');
        }

        // VAT node
        $this->addVatNode($itemNode, 'cac:ClassifiedTaxCategory', $line->getVatCategory(), $line->getVatRate());

        // BG-32: Item attributes
        foreach ($line->getAttributes() as $attribute) {
            $attributeNode = $itemNode->add('cac:AdditionalItemProperty');
            $attributeNode->add('cbc:Name', $attribute->getName());
            $attributeNode->add('cbc:Value', $attribute->getValue());
        }

        // Initial price node
        $priceNode = $xml->add('cac:Price');

        // Price amount
        $price = $line->getPrice();
        if ($price !== null) {
            $this->addAmountNode(
                $priceNode,
                'cbc:PriceAmount',
                $invoice->round($price, 'line/price'),
                $invoice->getCurrency()
            );
        }

        // Base quantity
        $baseQuantity = $line->getBaseQuantity();
        if ($baseQuantity != 1) {
            $priceNode->add('cbc:BaseQuantity', (string) $baseQuantity, ['unitCode' => $line->getUnit()]);
        }

        return $xml;
    }

    /**
     * Add attachment node
     * @param UXML       $parent     Parent element
     * @param Attachment $attachment Attachment instance
     */
    private function addAttachmentNode(UXML $parent, Attachment $attachment) {
        $xml = $parent->add('cac:AdditionalDocumentReference');
        $isInvoiceObjectReference = (!$attachment->hasExternalUrl() && !$attachment->hasContents());

        // BT-122: Supporting document reference
        $identifier = $attachment->getId();
        if ($identifier !== null) {
            $this->addIdentifierNode($xml, 'cbc:ID', $identifier);
        }

        // BT-18: Document type code
        if ($isInvoiceObjectReference) {
            // Code "130" MUST be used to indicate an invoice object reference
            // Not used for other additional documents
            $xml->add('cbc:DocumentTypeCode', '130');
        }

        // BT-123: Supporting document description
        $description = $attachment->getDescription();
        if ($description !== null) {
            $xml->add('cbc:DocumentDescription', $description);
        }

        // Attachment inner node
        if ($isInvoiceObjectReference) {
            return; // Skip inner node in this case
        }
        $attXml = $xml->add('cac:Attachment');

        // BT-125: Attached document
        if ($attachment->hasContents()) {
            $attrs = [];
            $mimeCode = $attachment->getMimeCode();
            $filename = $attachment->getFilename();
            if ($mimeCode !== null) {
                $attrs['mimeCode'] = $mimeCode;
            }
            if ($filename !== null) {
                $attrs['filename'] = $filename;
            }
            $attXml->add('cbc:EmbeddedDocumentBinaryObject', base64_encode($attachment->getContents()), $attrs);
        }

        // BT-124: External document location
        $externalUrl = $attachment->getExternalUrl();
        if ($externalUrl !== null) {
            $attXml->add('cac:ExternalReference')->add('cbc:URI', $externalUrl);
        }
    }
}

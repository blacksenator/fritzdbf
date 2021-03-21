<?php

namespace blacksenator\fritzdbf;

/**
  * The class provides functions to manipulate the address database FRITZ!Adr from AVM.
  * FRITZ!Adr is an address and phonebook for the more or less legacy programs
  * FRITZ!Fon, FRITZ!Data and FRITZ!Com. But still in use for FRITZ!fax:
  * https://ftp.avm.de/archive/fritz.box/tools/fax4box
  *
  * The database is a dBASE III file, the default name is 'FritzAdr.dbf'.
  *
  * There are three reasons for using this class:
  * 1. because of the difficulty of implementing the outdated extension for dBase
  * (PECL) for current PHP releases and platforms.
  * 2. due to the fact that for the purposes that only just one file with a defined
  * structure has to be written (no reading or manipulating data in records or whatever
  * else is conceivable)
  * 3. lastly, because it allows to write the data to memory instead of a local stored file.
  * So it is possible to create the file via ftp directly in the target directory.
  *
  * The DB analysis of a few FritzAdr.dbf files has surprisingly shown two variants with 19
  * e.g. 21 fields. Ultimately the 21er version works for me (default).
  *
  * Usage:
  * setting a new instance with the number of fields:
  *         $fritzAdr = new fritzdbf(19);    // number of fields
  *
  * appending a record:
  *         $fritzAdr->addRecord(['NAME' => 'John', 'VORNAME' => 'Doe']);
  *
  * receiving the data
  *         file_put_contents('FritzAdr.dbf', $fritzdbf->getDatabase());
  *
  * @author Volker Püschel <knuffy@anasco.de>
  * @copyright Volker Püschel 2019 - 2021
  * @license MIT
 **/

class fritzdbf
{
    const FRITZADRDEFINITION_19 = [
            ['BEZCHNG',   'C',  40],    // field  1
            ['FIRMA',     'C',  40],    // field  2
            ['NAME',      'C',  40],    // field  3
            ['VORNAME',   'C',  40],    // field  4
            ['ABTEILUNG', 'C',  40],    // field  5
            ['STRASSE',   'C',  40],
            ['PLZ',       'C',  10],
            ['ORT',       'C',  40],
            ['KOMMENT',   'C',  80],
            ['TELEFON',   'C',  64],    // field 10
            ['MOBILFON',  'C',  64],
            ['TELEFAX',   'C',  64],
            ['TRANSFER',  'C',  64],
            ['BENUTZER',  'C', 128],
            ['PASSWORT',  'C', 128],    // field 15
            ['TRANSPROT', 'C',   1],
            ['NOTIZEN',   'C', 254],
            ['EMAIL',     'C', 254],
            ['HOMEPAGE',  'C', 254],    // field 19
        ],
        FRITZADRDEFINITION_21 = [
            ['BEZCHNG',   'C',  40],    // field  1
            ['FIRMA',     'C',  40],    // field  2
            ['NAME',      'C',  40],    // field  3
            ['VORNAME',   'C',  40],    // field  4
            ['ABTEILUNG', 'C',  40],    // field  5
            ['STRASSE',   'C',  40],
            ['PLZ',       'C',  10],
            ['ORT',       'C',  40],
            ['KOMMENT',   'C',  80],
            ['TELEFON',   'C',  64],    // field 10
            ['TELEFAX',   'C',  64],
            ['TRANSFER',  'C',  64],
            ['TERMINAL',  'C',  64],
            ['BENUTZER',  'C', 128],
            ['PASSWORT',  'C', 128],    // field 15
            ['TRANSPROT', 'C',   1],
            ['TERMMODE',  'C',  40],
            ['NOTIZEN',   'C', 254],
            ['MOBILFON',  'C',  64],
            ['EMAIL',     'C', 254],    // field 20
            ['HOMEPAGE',  'C', 254]     // field 21
        ];

    private $dbDefinition,
            $numAttributes = 0,
            $headerLength  = 0,
            $recordLength  = 0,
            $table  = '',
            $numRecords    = 0,
            $fieldNames = [];

    /**
     * Initialize the class with basic settings
     *
     * @param int $fields
     * @return void
     */
    public function __construct(int $fields = 21)
    {
        if ($fields === 19) {
            $this->dbDefinition = self::FRITZADRDEFINITION_19;
            $this->recordLength = 1646;
        } elseif ($fields === 21) {
            $this->dbDefinition = self::FRITZADRDEFINITION_21;
            $this->recordLength = 1750;
        } else {
            $errorMsg = sprintf('FRITZ!Adr table definition must have 19 or 21 entities. You have specified %c!', $fields);
            throw new \Exception($errorMsg);
        }
        $this->numAttributes = $fields;
        $this->fieldNames = array_column($this->dbDefinition, 0);
    }

    /**
     * get the 32 byte header describing the kind of file
     *
     * @see ./docs/file structure.pdf
     *
     * Example:
     * 03 67 06 0d 03 20 20 20 c1 02 d6 06 20 20 20 20 20 20 20 20 20 20 20 20 20 20 20 20 01 20 20 20
     *
     * @return string $header
     */
    private function getHeader(): string
    {
        $lastUpdate = getdate(time());
        $this->headerLength = 33 + $this->numAttributes * 32;

        return
            pack('C', 0x03) .                          //  1      dBase version
            pack('C', $lastUpdate['year'] % 1000) .    //  2      date of last update (3 Bytes)
            pack('C', $lastUpdate['mon']) .            //  3      month
            pack('C', $lastUpdate['mday']) .           //  4      day
            pack('V', $this->numRecords) .             //  5 -  8 number of records in the table
            pack('v', $this->headerLength) .           //  9 - 10 number of bytes in the header
            pack('v', $this->recordLength) .           // 11 - 12 number of bytes in the record (1646 or 1750)
            str_pad('', 16, chr(0)) .                  /* 13 - 14 reserved; filled with zeros
                                                        * 15      dBase IV filed; filled with zero
                                                        * 16      dBase IV filed; filled with zero
                                                        * 17 - 20 reserved for multi-user processing
                                                        * 21 - 28 reserved for multi-user processing */
            pack('C', 0x01) .                          // 29      mdx file exist
            str_pad('', 3, chr(0));                    /* 30      language code
                                                        * 31 - 32 reserved; filled with zeros */
    }

    /**
     * get the 32 byte descriptor describing each field (entity)
     *
     * @see ./docs/file structure.pdf
     *
     * Example (1 field):
     * 42 45 5a 43 48 4e 47 20 20 20 20 43 20 20 20 20 28 20 20 20 20 20 20 20 20 20 20 20 20 20 20 01
     *
     * @return string $entities
     */
    private function getFieldDescriptor(): string
    {
        $entities = null;
        foreach ($this->dbDefinition as $attribute) {
            $entity =
                str_pad($attribute[0], 10, chr(0)) .   // field name filled up with zeros
                pack('C', '0') .                       // separator
                substr($attribute[1], 0, 1) .          // field type
                str_pad('', 4, chr(0)) .               // reserved; filled with zeros
                pack('C', $attribute[2]) .             // filed length
                pack('C', '0') .                       // field decimal count
                str_pad('', 14, chr(0));               // reserved; filled with zeros
            $entities .= $entity;
        }

        return $entities;
    }

    /**
     * get an assoziativ array according to the db-definition:
     *
     * @see ./docs/table structure.pdf
     *
     * Each field name to one entry filled up with blanks to the given
     * field length
     *
     * @return array $record
     */
    private function getEmptyRecord(): array
    {
        $record = [];
        foreach ($this->dbDefinition as $field) {
            $record[$field[0]] = str_pad('', $field[2], ' ');      // fill every field with the designated amont of space
        }

        return $record;
    }

    /**
     * Set a value to a designated field
     *
     * @param array $record  assoziative array of fields (e.g. ['NAME' => '', 'VORNAME' => ''])
     * @param string $field  e.g. 'NAME'
     * @param string $value  e.g. 'Doe'
     * @return array $record
     */
    private function setFieldValue(array $record, $field, $value): array
    {
        if (in_array($field, $this->fieldNames)) {
            $fieldLength = strlen($record[$field]);                    // count length of field
            $value = substr($value, 0, $fieldLength);                  // truncates the value to the field length
            $record[$field] = str_pad($value, $fieldLength, ' ');      // fills up with spaces
        }

        return $record;
    }

    /**
     * add a new record to the database
     *
     * @param array $record assoziative array of fields (e.g. ['NAME' => 'Doe', 'VORNAME' => 'John'])
     * @return void
     */
    public function addRecord(array $record)
    {
        $newRecord = $this->getEmptyRecord();          // get an new (empty) record
        foreach ($record as $field => $value) {
            if (isset($value)) {
                // transfer the given values into the new record
                $newRecord = $this->setFieldValue($newRecord, $field, $value);
            }
        }
        $dataset = pack('C', 0x20) . implode($newRecord);   // start byte (0x2a if record is marked for deletion)
        $this->table .= $dataset;               // append the dataset to the global var table
        $this->numRecords++;                    // increment the record counter; needed in setHeader()
    }

    /**
     * get the dBASE data well formated
     *
     * @return string $dataBase
     */
    public function getDatabase()
    {
        $dataBase = $this->getHeader() .
                    $this->getFieldDescriptor() .
                    pack('C', 0x0d) .
                    $this->table .
                    pack('C', 0x1a);

        return $dataBase;
    }
}

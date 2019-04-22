# dBASE file generator for AVM FRITZ!Fax (fax4box)

The class provides functions to manipulate the address database FRITZ!Adr from AVM. FRITZ!Adr is an address and phonebook for the more or less legacy programs FRITZ!Fon, FRITZ!Data and FRITZ!Com. But still in use for FRITZ!fax: https://ftp.avm.de/archive/fritz.box/tools/fax4box

The database is a dBASE III file, the default name is 'FritzAdr.dbf'.

There are three reasons for using this class:
  1. because of the difficulty of implementing the outdated extension for dBase (PECL) for current PHP releases and platforms.
  2. due to the fact that for the purposes that only just one file with a defined structure has to be written (no reading or manipulating data in records or whatever else is conceivable)
  3. lastly, because it allows to write the data to memory instead of a local stored file. So it is possible to create the file via ftp directly in the target directory.

The DB analysis of a few FritzAdr.dbf files has surprisingly shown two variants with 19 e.g. 21 fields. Ultimately the 21er version works for me (default).

## Features

  * Does create a dBase file in memory instead of writing it directly to a file (like the outdated PECL version)
  * limited functionality: just addRecord! If you think more features are needed, fork this repo and feel free to contribute!

## Requirements

  * PHP 7.0
  * Composer (follow the installation guide at https://getcomposer.org/download/)

## Installation

You can install it through Composer:

    $ composer require blacksentor/fritzdbf

or

    git clone https://github.com/blacksenator/fritzdbf.git

## Usage

setting a new instance with the number of fields:

    $fritzDbf = new fritzdbf(19);    // number of fields

appending a record:

    $fritzDbf->addRecord(['NAME' => 'John', 'VORNAME' => 'Doe']);

receiving the data:

    $fileData = $fritzDbf->getDatabase());

### Samples

    <?php

    use blacksenator\fritzdbf;

    $newData = ['BEZCHNG' => 'Maria Mustermann',   // Feld 1
                'FIRMA'   => 'Bundesdruckerei',
                'NAME'    => 'Mustermann',
                'VORNAME' => 'Erika',
                'TELEFON' => '03025980'
        ];

    $fritzDbf = new fritzdbf();
    $fritzDbf->addRecord($newData);
    file_put_contents('FritzAdr.dbf', $fritzDbf->getDatabase());

or directly via ftp (the reason why I coded this)

    <?php

    use blacksenator\fritzdbf;

    $newData = ['BEZCHNG' => 'Max Mustermann',   // Feld 1
                'FIRMA'   => 'Bundesdruckerei',
                'NAME'    => 'Mustermann',
                'VORNAME' => 'Max',
                'TELEFON' => '03025980'
        ];

    $ftp_conn = ftp_connect($ftpserver);
    ftp_login($ftp_conn, $user, $password);
    ftp_chdir($ftp_conn, $destination);
    $memstream = fopen('php://memory', 'r+');

    $fritzDbf = new fritzdbf();
    $fritzDbf->addRecord($newData);

    $memstream = $fritzDbf->getDatabase();
    rewind($memstream);
    ftp_fput($ftp_conn, 'FritzAdr.dbf',  $memstream, FTP_BINARY);

## License
This script is released under MIT license.

## Authors
Copyright (c) 2019 Volker Püschel
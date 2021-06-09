<?php

declare(strict_types=1);

/*
 * (c) 2021 Michael Joyce <mjoyce@sfu.ca>
 * This source file is subject to the GPL v2, bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Services\ImportService;
use App\Util\SacramentColumnDefinitions as S;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * AppImportSacramentCommand command.
 */
class ImportSacramentCommand extends Command {
    private EntityManagerInterface $em;

    private ImportService $importer;

    public function __construct(EntityManagerInterface $em, ImportService $importer, $name = null) {
        parent::__construct($name);
        $this->importer = $importer;
        $this->em = $em;
    }

    /**
     * Configure the command.
     */
    protected function configure() : void {
        $this
            ->setName('app:import:sacrament')
            ->setDescription('Import sacramental data from one or more CSV files')
            ->addArgument('files', InputArgument::IS_ARRAY, 'List of CSV files to import')
            ->addOption('skip', null, InputOption::VALUE_REQUIRED, 'Number of header rows to skip', 1);
    }

    function preprocess(array $row) : array {
        return array_map(function($d) {
            $s = mb_convert_encoding($d, 'UTF-8', 'UTF-8');
            return preg_replace("/^\s+|\s+$/u", '', $s);
        }, $row);
    }

    /**
     * @throws Exception
     */
    protected function process($row) {
        $person = $this->importer->findPerson($row[S::first_name], $row[S::last_name], $row[S::race_id], $row[S::sex]);
        $this->importer->setWrittenRace($person, $row); // includes race_id;
        $this->importer->setStatus($person, $row);
        $this->importer->addManumission($person, $row);
        $this->importer->addAliases($person, $row);
        $this->importer->addOccupations($person, $row);
        $this->importer->setNative($person, $row);
        $this->importer->addBirth($person, $row);
        $this->importer->setBirthStatus($person, $row);
        $baptism = $this->importer->addBaptism($person, $row);
        $this->importer->addParents($person, $row);
        $this->importer->addGodParents($person, $row, $baptism);
        $marriage = $this->importer->addMarriage($person, $row);
        $this->importer->addSpouse($person, $row);
        $this->importer->addMarriageWitnesses($person, $row, $marriage);
        $this->importer->addDeath($person, $row);
        $this->importer->addResidences($person, $row);
        $person->setNotes($row[S::notes]);
    }

    /**
     * Execute the command.
     *
     * @param inputInterface $input
     *                              Command input, as defined in the configure() method
     * @param outputInterface $output
     *                                Output destination
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void {
        $files = $input->getArgument('files');
        $skip = $input->getOption('skip');

        foreach ($files as $file) {
            $handle = fopen($file, 'r');

            for ($i = 1; $i <= $skip; $i++) {
                fgetcsv($handle);
            }
            for(; $row = fgetcsv($handle); $i++) {
                $row = array_map(static fn($data) => mb_convert_encoding($data, 'UTF-8', 'UTF-8'), $row);

                try {
                    $this->process($row);
                    $this->em->beginTransaction();
                    $this->em->flush();
                    $this->em->commit();
                } catch (Exception $e) {
                    $output->writeln("{$file}:{$i} - {$e->getMessage()}");
                    $this->em->rollback();
                    $this->em->clear();
                }
            }
        }
    }
}

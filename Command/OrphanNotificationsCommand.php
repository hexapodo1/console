<?php
namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Utils\Connection;

class OrphanNotificationsCommand extends Command
{
    protected function configure()
    {
      $this
          ->setName('np:orphan:delete')
          ->setDescription('Removes the orphan Notification Policies for scans.')
          ->setHelp("This command deletes the orphan Notification Policies for scans, you can find a detailed log in the logs folder that contains the notification policies that were deleted.")
          ->addArgument('datacenter', InputArgument::REQUIRED, 'The datacenter where will search the notification policies (px, denver, ashburn, uk, acheron).')
          ->addArgument('simulated', InputArgument::OPTIONAL, '1: simulates the deleting.')
          ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // load the logger
        $log = $this->getApplication()->getLog();

        // Load customers from yaml file
        $this->getApplication()->loadYaml('customers.yml');

        // parameters
        $container = $this->getApplication()->getContainer();
        $dataCenter = $input->getArgument('datacenter');
        $simulated = $input->getArgument('simulated');
        $customers = $container->getParameter('customers')[$dataCenter];
        $parameters = $container->getParameter($dataCenter);
        $onlyForScans = $parameters['onlyForScans'];

        $connection = $container->get('connection');
        $connection->init($dataCenter);

        // start
        $output->writeln('<info>Processing start</>');
        $output->writeln('');
        $listNPDeleted = array();
        $stats = array();
        $curl = $container->get('curl');
        foreach ($customers as $customerId) {
            $numberNP = 0;
            $numberNPSkipped = 0;
            $numberNPDeleted = 0;
            $output->writeln('<fg=red>******************** Customer: ' . $customerId . " ********************</>");
            $curl->init($dataCenter, 'policies', "?customer_id=" . $customerId);
            $notificationPolicies = $curl->execute(1);
            if(isset($notificationPolicies['notification_policies'])) {
                foreach ($notificationPolicies['notification_policies'] as $notificationPolicy) {
                    $output->writeln('');
                    $output->writeln('<info>*</> ' . $notificationPolicy['name']);
                    if ( ! $onlyForScans || ($onlyForScans && $notificationPolicy['alert_definition_type_id'] === '705A0DE8-AC55-BCC7-B55C-F35720D40E56') ) { // <- definition type for scans
                        $numberNP++;
                        if (isset($notificationPolicy['notification_targets'])) {
                            $scanPolicyId = $notificationPolicy['external_reference']['unique_id'];
                            $sql = "select deleted from policy_tbl where policy_id=" . $scanPolicyId;
                            $results = $connection->query($sql);
                            if (
                                (isset($results[0]) && isset($results[0]['deleted']) && 
                                ( (int) $results[0]['deleted'] === 1) || $results[0]['deleted'] === NULL )
                            ) {
                                $numberNPDeleted++;
                                $listNPDeleted[$customerId][] = 	array(
                                    'notificationPolicy' => array(
                                        'id'					=> $notificationPolicy['id'],
                                        'name'				=> $notificationPolicy['name']
                                    )
                                );
                                if ( (int) $simulated === 1) {
                                    $log->debug('(Simulated) NP', $notificationPolicy);
                                    $output->writeln('<fg=red>Deleted</> <comment>(simulated)</> ');
                                } else {
                                    $log->debug('NP', $notificationPolicy);
                                    $curl->init($dataCenter, 'policies', $notificationPolicy['id']);
                                    $curl->delete();
                                    $output->writeln('<fg=red>Deleted</> ');
                                }
                            }
                        }
                    } else {
                        $numberNPSkipped++;
                        $output->writeln("\t<comment>Skipped</>");
                    }
                }
            }
            $stats[$customerId] = array(
                'numberNP'   => $numberNP,
                'numberNPSkipped'   => $numberNPSkipped,
                'numberNPDeleted'    => $numberNPDeleted
            );
        }

        $output->writeln("");
        $output->writeln("<info>Completion of processing</>");
        $output->writeln("");
        $output->writeln("");

        // shows a table with notification policies with targets that belong to other customer. 
        foreach ($listNPDeleted as $cid => $customer) {
            $output->writeln("");
            $output->writeln('<fg=red>************* Deleted Notification Policies for customer: ' . $cid . " *************</>");
            $tableNPDeleted = new Table($output);
            $tableNPDeleted
                ->setHeaders(array('', 'NP id', 'NP name'));
            $i = 0;
            foreach ($customer as $element) {
                $tableNPDeleted->addRows(array(
                    array(
                        ++$i,
                        $element['notificationPolicy']['id'],
                        $element['notificationPolicy']['name'],
                    ),
                ));
            }
            $tableNPDeleted->render();
            $output->writeln("");
        }

        // shows summary table
        $i = 0;
        $output->writeln("<fg=red>******************** SUMMARY ********************</>");
        $tableSummary = new Table($output);
        $tableSummary
            ->setHeaders(array('', 'Customer Id', 'NP Processed', 'NP skipped', 'NP Deleted'));
        $numberNP = 0;
        $numberNPSkipped = 0;
        $numberNPDeleted = 0;
        foreach ($stats as $cid => $customer) {
            $tableSummary->addRows(array(
                array(
                  ++$i,
                  $cid,
                  $customer['numberNP'],
                  $customer['numberNPSkipped'],
                  $customer['numberNPDeleted']
                ),
            ));
            $numberNP += $customer['numberNP'];
            $numberNPSkipped += $customer['numberNPSkipped'];
            $numberNPDeleted += $customer['numberNPDeleted'];
        }
        $tableSummary
            ->addRows(array(
              new TableSeparator(),
              array('', 'Customer Id', 'NP Processed', 'NP skipped', 'NP Deleted')));

        $tableSummary->addRows(array(
            new TableSeparator(),
            array(
                'Total',
                '',
                $numberNP,
                $numberNPSkipped,
                $numberNPDeleted,
            )
        ));

        $tableSummary->render();
        $output->writeln("");
        $output->writeln("");

    }

}

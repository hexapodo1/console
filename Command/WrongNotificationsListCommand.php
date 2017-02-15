<?php
namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;

class WrongNotificationsListCommand extends Command
{
    protected function configure()
    {
      $this
          ->setName('np:contacts:wrong')
          ->setDescription('Obtains a list of notification policies with wrong contacts.')
          ->setHelp("This command Obtains a list of notification policies with wrong contacts, example: contacts that belong to other customer or notification policies with contacts that don't exist.")
          ->addArgument('datacenter', InputArgument::REQUIRED, 'The datacenter where will search the notification policies (px, denver, ashburn, uk, acheron).')
          ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Load customers from yaml file
        $this->getApplication()->loadYaml('customers.yml');
        
        // parameters
        $container = $this->getApplication()->getContainer();
        $dataCenter = $input->getArgument('datacenter');
        $parameters = $container->getParameter($dataCenter);
        $customers = $container->getParameter('customers')[$dataCenter];
        $parameters = $container->getParameter($dataCenter);
        $onlyForScans = $parameters['onlyForScans'];
        $customerId = $parameters['customerId'];
        $server   = $parameters['server'];
        $port     = $parameters['port'];
        $headers  = $parameters['headers'];
        $endpointTargets  = "http://" . $server . ":" . $port 
            . $parameters['endpointTargets'];
            
        // start    
        $output->writeln('<info>Processing start</>');
        $output->writeln('');
        $listNPwithTargetUnknown = array();
        $listNPdoesntExist = array();
        $stats = array();
        foreach ($customers as $customerId) {
            $numberNP = 0;
            $numberNT = 0;
            $numberNPSkipped = 0;
            $numberNPFailed = 0;
            $numberNTFailed = 0;
            $numberNTWrong = 0;
            $output->writeln('<fg=red>******************** Customer: ' . $customerId . " ********************</>");
            $endpoint  = "http://" . $server . ":" . $port 
                . $parameters['endpoint']
                . "?customer_id=" . $customerId;

            $ch = curl_init();								// Initiate curl
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Set The Response Format to Json
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Will return the response, if false it print the response
            curl_setopt($ch, CURLOPT_URL, $endpoint); // Set the url
            $notificationPoliciesJson = curl_exec($ch);						// Execute
            curl_close($ch);								// Closing
            $notificationPolicies = json_decode($notificationPoliciesJson, true);
            //For each notification policy I need to ask if the target(s) is/are of the same customer
            if(isset($notificationPolicies['notification_policies'])) {
                foreach ($notificationPolicies['notification_policies'] as $notificationPolicy) {
                    $NPFailed = false;
                    $output->writeln('');
                    $output->writeln('<info>*</> ' . $notificationPolicy['name']);
                    if ( ! $onlyForScans || ($onlyForScans && $notificationPolicy['alert_definition_type_id'] === '705A0DE8-AC55-BCC7-B55C-F35720D40E56') ) { // <- definition type for scans
                        $numberNP++;
                        if (isset($notificationPolicy['notification_targets'])) {
                            // get data for each Notification target
                            foreach ($notificationPolicy['notification_targets'] as $notificationTarget) {
                                $ch2 = curl_init();								 // Initiate curl
                                curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers); // Set The Response Format to Json
                                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true); // Will return the response, if false it print the response
                                curl_setopt($ch2, CURLOPT_URL, $endpointTargets . "/" . $notificationTarget); // Set the url
                                $ntJson = curl_exec($ch2);						 // Execute
                                curl_close($ch2);								 // Closing
                                $nt = json_decode($ntJson, true);
                                $output->writeln("\t<info>" .  $nt['target'] ."</>");
                                if (isset($nt) && array_key_exists("id", $nt)) {
                                    $numberNT++;
                                    if ($customerId != $nt['customer_id']) {
                                        $NPFailed = true;
                                        $numberNTWrong++;
                                        $listNPwithTargetUnknown[$customerId][] = 	array(
                                            'notificationPolicy' => array(
                                                'id'					=> $notificationPolicy['id'],
                                                'name'				=> $notificationPolicy['name'],
                                                'customer_id' => $notificationPolicy['customer_id']
                                            ),
                                            'NotificationTarget' => array(
                                                'id'					=> $nt['id'],
                                                'target'			=> $nt['target'],
                                                'customer_id' => $nt['customer_id']
                                            )
                                        );
                                    }
                                } else {
                                    $NPFailed = true;
                                    $numberNTFailed++;
                                    $listNPdoesntExist[$customerId][] = 	array(
                                        "nt"  => $notificationTarget,
                                        "np"  => $notificationPolicy['id'],
                                        "npName"  => $notificationPolicy['name'],
                                        "msg" => $nt
                                    );
                                }
                            }
                        } 
                        if ($NPFailed) {
                          $numberNPFailed++;
                        }
                    } else {
                        $numberNPSkipped++;
                        $output->writeln("\t<comment>Skipped</>");
                    }
                    
                    
                }
            }
            $stats[$customerId] = array(
                'numberNP'          => $numberNP,
                'numberNT'          => $numberNT,
                'numberNPSkipped'   => $numberNPSkipped,
                'numberNPFailed'    => $numberNPFailed,
                'numberNTFailed'    => $numberNTFailed,
                'numberNTWrong'    => $numberNTWrong
            );
        }
        
        $output->writeln("");
        $output->writeln("<info>Completion of processing</>");
        $output->writeln("");
        $output->writeln("");

        // shows a table with notification policies with targets that belong to other customer. 
        foreach ($listNPwithTargetUnknown as $cid => $customer) {
            $output->writeln("");
            $output->writeln('<fg=red>******************** Customer: ' . $cid . " ********************</>");
            $tableNPwithTargetUnknown = new Table($output);
            $tableNPwithTargetUnknown
                ->setHeaders(array('', 'NP id', 'NP name', 'NP CID','NT id','NT name','NT CID'));
            $i = 0;
            foreach ($customer as $element) {
                $tableNPwithTargetUnknown->addRows(array(
                    array(
                      ++$i,
                      $element['notificationPolicy']['id'],
                      $element['notificationPolicy']['name'],
                      $element['notificationPolicy']['customer_id'],
                      $element['NotificationTarget']['id'],
                      $element['NotificationTarget']['target'],
                      $element['NotificationTarget']['customer_id'],
                    ),
                ));
            }    
            $tableNPwithTargetUnknown->render();
            $output->writeln("");
        }
        
        // shows a table with notification policies with targets that doesn't exist.
        foreach ($listNPdoesntExist as $cid => $customer) {
            $output->writeln("");
            $output->writeln('<fg=red>******************** Customer: ' . $cid . " ********************</>");
            $tableNPdoesntExist = new Table($output);
            $tableNPdoesntExist->setHeaders(array('', 'NP ID', 'NP NAME', 'NT ID', 'Message'));
            $i = 0;
            foreach ($customer as $element) {
                $tableNPdoesntExist->addRows(array(
                    array(
                      ++$i,
                      $element['np'],
                      $element['npName'],
                      $element['nt'],
                      json_encode($element['msg'])
                    ),
                ));
            }    
            $tableNPdoesntExist->render();
            $output->writeln("");
          }
          
          // shows summary table
          $i = 0;
          $output->writeln("<fg=red>******************** SUMMARY ********************</>");
          $tableSummary = new Table($output);
          $tableSummary
              ->setHeaders(array('', 'Customer Id', 'NP Processed', 'NP skipped', 'NP Failed', 'NT Processed', 'NT Failed', 'NT Wrong'));
          $numberNP = 0;
          $numberNT = 0;
          $numberNPSkipped = 0;
          $numberNPFailed = 0;
          $numberNTFailed = 0;
          $numberNTWrong = 0;
          foreach ($stats as $cid => $customer) {
              $tableSummary->addRows(array(
                  array(
                    ++$i,
                    $cid,
                    $customer['numberNP'],
                    $customer['numberNPSkipped'],
                    $customer['numberNPFailed'],
                    $customer['numberNT'],
                    $customer['numberNTFailed'],
                    $customer['numberNTWrong']
                  ),
              ));
              $numberNP += $customer['numberNP'];
              $numberNT += $customer['numberNT'];
              $numberNPSkipped += $customer['numberNPSkipped'];
              $numberNPFailed += $customer['numberNPFailed'];
              $numberNTFailed += $customer['numberNTFailed'];
              $numberNTWrong += $customer['numberNTWrong'];
          }
          $tableSummary
              ->addRows(array(
                new TableSeparator(),
                array('', 'Customer Id', 'NP Processed', 'NP skipped', 'NP Failed', 'NT Processed', 'NT Failed', 'NT Wrong')));
          
          $tableSummary->addRows(array(
              new TableSeparator(),
              array(
                  'Total',
                  '',
                  $numberNP,
                  $numberNPSkipped,
                  $numberNPFailed,
                  $numberNT,
                  $numberNTFailed,
                  $numberNTWrong
              )
          ));
          
          $tableSummary->render();
          $output->writeln("");
          $output->writeln("Customers processed: " . count($customers));
          if ($numberNT >0 ) {
              $percentage = ($numberNTWrong / $numberNT) * 100;
          } else {
              $percentage = '---';
          }
          $output->write("Percentage of wrong NT: ");
          $output->writeln(is_string($percentage) ? $percentage : number_format($percentage, 1));

          $output->writeln("");
        
    }

}

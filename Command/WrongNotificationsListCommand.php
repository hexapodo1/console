<?php
namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

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
        // Load customer from yaml file
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
        
        foreach ($customers as $customerId) {
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
            //For each notification policy I need to ask if the target is of the same customer
            foreach ($notificationPolicies['notification_policies'] as $notificationPolicy) {
                $output->writeln('');
                $output->writeln('<info>*</> ' . $notificationPolicy['name']);
                if (!$onlyForScans || $notificationPolicy['alert_definition_type_id'] === '705A0DE8-AC55-BCC7-B55C-F35720D40E56') {
                    if (isset($notificationPolicy['notification_targets'])) {
                        # get data for each Notification target
                        foreach ($notificationPolicy['notification_targets'] as $notificationTarget) {
                            $ch2 = curl_init();								 // Initiate curl
                            curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers); // Set The Response Format to Json
                            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true); // Will return the response, if false it print the response
                            curl_setopt($ch2, CURLOPT_URL, $endpointTargets . "/" . $notificationTarget); // Set the url
                            $ntJson = curl_exec($ch2);						 // Execute
                            curl_close($ch2);								 // Closing
                            $nt = json_decode($ntJson, true);
                            $output->writeln("\t<info>" .  $nt['target'] ."</>");;
                            if (isset($nt) && array_key_exists("id", $nt)) {
                                if ($customerId != $nt['customer_id']) {
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
                                $listNPdoesntExist[$customerId][] = 	array(
                                    "nt"  => $notificationTarget,
                                    "msg" => $nt
                                );
                            }
                        }
                    } 
                } else {
                    $output->writeln("\t<comment>Ignored</>");
                }
            }
        }
        
        var_dump($listNPdoesntExist);
        $output->writeln("");
        $output->writeln("<info>Completion of processing</>");
        $output->writeln("");
        $output->writeln("");

        foreach ($listNPwithTargetUnknown as $customer) {
            $tableNPwithTargetUnknown = new Table($output);
            $tableNPwithTargetUnknown
                ->setHeaders(array('NP id', 'NP name', 'NP CID','NT id','NT name','NT CID'));
            foreach ($customer as $element) {
                $tableNPwithTargetUnknown->addRows(array(
                    array(
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

            $tableNPdoesntExist = new Table($output);
            $tableNPdoesntExist
                ->setHeaders(array('NT name','message'));
            foreach ($customer as $element) {
                $tableNPdoesntExist->addRows(array(
                    array(
                      $element['nt'],
                      json_encode($element['msg'])
                    ),
                ));
            }    
            $tableNPdoesntExist->render();
            $output->writeln("");
          }
          $output->writeln("");
        
    }

}

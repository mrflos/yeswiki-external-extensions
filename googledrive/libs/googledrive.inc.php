<?php

/** collaborative_doc() - Ajoute un lien vers un document collaboratif (google ou etherpad)
 *
 * @param    mixed   L'objet QuickForm du formulaire
 * @param    mixed   Le tableau des valeurs des différentes option pour l'élément liste
 * @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par défaut
 * @return   void
 */
function collaborative_doc(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
    $valliste = array(
        'wordprocessing' => 'Traitement de texte',
        "spreadsheet" => 'Tableur',
        "presentation" => 'Diapositives de présentation',
        "etherpad" => 'Prise de note collaborative Etherpad'
    );

    if ($mode == 'saisie') {

        // test de l'existance d'un fichier, dans ce cas on ne propose pas sa creation
        $urldoc = '';
        if (isset($valeurs_fiche[$tableau_template[1].$valeurs_fiche[$tableau_template[1]] . '_url'])) {
            $urldoc = $valeurs_fiche[$tableau_template[1].$valeurs_fiche[$tableau_template[1]] . '_url'];
        }
        //var_dump($urldoc, empty($urldoc));

        if (empty($urldoc)) {
          $bulledaide = '';
          if (isset($tableau_template[10]) && $tableau_template[10] != '') {
              $bulledaide = ' &nbsp;&nbsp;<img class="tooltip_aide" title="' .
              htmlentities($tableau_template[10], ENT_QUOTES, TEMPLATES_DEFAULT_CHARSET) .
              '" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
          }

          $select_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-xs-3">' . "\n";
          if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
              $select_html.= '<span class="symbole_obligatoire">*&nbsp;</span>' . "\n";
          }
          $select_html.= $tableau_template[2] . $bulledaide . ' : </label>' . "\n" .
              '<div class="controls col-xs-8">' . "\n";
            $select_html.=  '<select';
            $select_attributes = ' class="form-control" id="'.$tableau_template[1].'" name="'.$tableau_template[1].'"';

            if (isset($tableau_template[8]) && $tableau_template[8] == 1) {
                $select_attributes.= ' required="required"';
            }
            $select_html.= $select_attributes . '>' . "\n";

            if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]] != '') {
                $def = $valeurs_fiche[$tableau_template[1]];
            } else {
                $def = $tableau_template[5];
            }

            if ($def == '' && ($tableau_template[4] == '' || $tableau_template[4] <= 1) || $def == 0) {
                $select_html.= '<option value="" selected="selected">Aucun</option>' . "\n";
            }

            if (is_array($valliste)) {
                foreach ($valliste as $key => $label) {
                    $select_html.= '<option value="' . $key . '"';
                    if ($def != '' && $key == $def) {
                        $select_html.= ' selected="selected"';
                    }
                    $select_html.= '>' . $label . '</option>' . "\n";
                }
            }
            $select_html.= "</select>\n</div>\n</div>\n";
        } else {
          $select_html = '<div class="control-group form-group">' . "\n" . '<label class="control-label col-sm-3">' . "\n";
          $select_html .= 'Lien vers le document : </label>' . "\n" .
              '<div class="controls col-sm-9">' . "\n". '<a href="'.$urldoc.'">'.$urldoc.'</a>';
            $select_html.= '<input type="hidden" value="'.$urldoc.'" name="'.
                $tableau_template[1] . $valeurs_fiche[$tableau_template[1]] . '_url'.'">'."\n</div>\n</div>\n";
        }
        $formtemplate->addElement('html', $select_html);
    } elseif ($mode == 'requete') {
        //si le doc n'existe pas, on le crée
        if (!isset($valeurs_fiche[$tableau_template[1] . $valeurs_fiche[$tableau_template[1]] . '_url']) ||
            $valeurs_fiche[$tableau_template[1] . $valeurs_fiche[$tableau_template[1]] . '_url'] == '') {
            if ($valeurs_fiche[$tableau_template[1]] == 'etherpad') {
                return array(
                    $tableau_template[1] => $valeurs_fiche[$tableau_template[1]],
                    $tableau_template[1].$valeurs_fiche[$tableau_template[1]].'_url'
                        => $GLOBALS['wiki']->config['etherpad_url']
                            .genere_nom_wiki($valeurs_fiche['bf_titre'])
                );
            } else {
                // documents google
                $client = new Google_Client();
                $client->setApplicationName("Collaborative docs");
                $service = new Google_Service_Drive($client);

                if (isset($_SESSION['service_token'])) {
                    $client->setAccessToken($_SESSION['service_token']);
                }
                $key = file_get_contents($GLOBALS['wiki']->config['key_file_location']);
                $cred = new Google_Auth_AssertionCredentials(
                    $GLOBALS['wiki']->config['service_account_name'],
                    array('https://www.googleapis.com/auth/drive'),
                    $key
                );

                $client->setAssertionCredentials($cred);
                if ($client->getAuth()->isAccessTokenExpired()) {
                    $client->getAuth()->refreshTokenWithAssertion($cred);
                }

                $_SESSION['service_token'] = $client->getAccessToken();

                //Insert a file
                $file = new Google_Service_Drive_DriveFile();
                $file->setTitle($valeurs_fiche['bf_titre']);
                if (isset($valeurs_fiche['bf_description']) && $valeurs_fiche['bf_description'] != '') {
                    $file->setDescription($valeurs_fiche['bf_description']);
                }
                if ($valeurs_fiche[$tableau_template[1]] == 'spreadsheet') {
                    $file->setMimeType('application/vnd.google-apps.spreadsheet');
                } elseif ($valeurs_fiche[$tableau_template[1]] == 'presentation') {
                    $file->setMimeType('application/vnd.google-apps.presentation');
                } else {
                    $file->setMimeType('application/vnd.google-apps.document');
                }

                // dossier de destination
                $parent = new Google_Service_Drive_ParentReference();
                $parent->setId($GLOBALS['wiki']->config['folder_id']);
                // cet id correspond à un dossier partage du drive
                // (avec le $GLOBALS['wiki']->config['service_account_name'] en partage)
                $file->setParents(array($parent));

                $createdFile = $service->files->insert($file);

                // droits d'acces
                $newPermission = new Google_Service_Drive_Permission();

                $newPermission->setValue('anyone');
                $newPermission->setType('anyone');
                $newPermission->setRole('writer');
                $service->permissions->insert($createdFile['id'], $newPermission);

                return array(
                    $tableau_template[1] => $valeurs_fiche[$tableau_template[1]],
                    $tableau_template[1].$valeurs_fiche[$tableau_template[1]].'_url' => $createdFile['alternateLink']
                );
            }
        } else {
            return array(
                $tableau_template[1] => $valeurs_fiche[$tableau_template[1]],
                $tableau_template[1].$valeurs_fiche[$tableau_template[1]].'_url'
                    => $valeurs_fiche[$tableau_template[1].'_url']
            );
        }
    } elseif ($mode == 'html') {
        $html = '';
        if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]] != '') {
            $html.= '<div class="BAZ_rubrique" data-id="' . $tableau_template[1] . '">' . "\n" .
                '<span class="BAZ_label">' . $tableau_template[2] . '&nbsp;:</span>' . "\n" .
                '<span class="BAZ_texte">'."\n".$valliste[$valeurs_fiche[$tableau_template[1]]]."\n".'</a></span>'."\n".
                '</div> <!-- /.BAZ_rubrique -->' . "\n";
        }
        if (isset($valeurs_fiche[$tableau_template[1] . $valeurs_fiche[$tableau_template[1]] . '_url']) &&
            $valeurs_fiche[$tableau_template[1] . $valeurs_fiche[$tableau_template[1]] . '_url'] != '') {
            $html.= '<div class="BAZ_rubrique" data-id="' . $tableau_template[1] . '_url' . '">' . "\n" .
                '<span class="BAZ_label">Lien vers le document&nbsp;:</span>' . "\n" .
                '<span class="BAZ_texte">
                <a href="'.$valeurs_fiche[$tableau_template[1].$valeurs_fiche[$tableau_template[1]].'_url'].
                '" target="_blank">'."\n".
                $valeurs_fiche[$tableau_template[1].$valeurs_fiche[$tableau_template[1]].'_url']."\n".
                '</a></span>' . "\n" . '</div> <!-- /.BAZ_rubrique -->' . "\n";
        }

        return $html;
    }
}

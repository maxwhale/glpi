<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * CommonITILObject Class
**/
abstract class CommonITILObject extends CommonDBTM {

   /// Users by type
   protected $users       = [];
   public $userlinkclass  = '';
   /// Groups by type
   protected $groups      = [];
   public $grouplinkclass = '';

   /// Suppliers by type
   protected $suppliers      = [];
   public $supplierlinkclass = '';

   /// Use user entity to select entity of the object
   protected $userentity_oncreate = false;


   /// From CommonDBTM
   public $notificationqueueonaction = true;

   const MATRIX_FIELD         = '';
   const URGENCY_MASK_FIELD   = '';
   const IMPACT_MASK_FIELD    = '';
   const STATUS_MATRIX_FIELD  = '';


   // STATUS
   const INCOMING      = 1; // new
   const ASSIGNED      = 2; // assign
   const PLANNED       = 3; // plan
   const WAITING       = 4; // waiting
   const SOLVED        = 5; // solved
   const CLOSED        = 6; // closed
   const ACCEPTED      = 7; // accepted
   const OBSERVED      = 8; // observe
   const EVALUATION    = 9; // evaluation
   const APPROVAL      = 10; // approbation
   const TEST          = 11; // test
   const QUALIFICATION = 12; // qualification

   const TIMELINE_NOTSET   = 0;
   const TIMELINE_LEFT     = 1;
   const TIMELINE_MIDLEFT  = 2;
   const TIMELINE_MIDRIGHT = 3;
   const TIMELINE_RIGHT    = 4;



   function post_getFromDB() {
      $this->loadActors();
   }


   /**
    * @since 0.84
   **/
   function loadActors() {

      if (!empty($this->grouplinkclass)) {
         $class        = new $this->grouplinkclass();
         $this->groups = $class->getActors($this->fields['id']);
      }

      if (!empty($this->userlinkclass)) {
         $class        = new $this->userlinkclass();
         $this->users  = $class->getActors($this->fields['id']);
      }

      if (!empty($this->supplierlinkclass)) {
         $class            = new $this->supplierlinkclass();
         $this->suppliers  = $class->getActors($this->fields['id']);
      }
   }


   /**
    * Retrieve an item from the database with datas associated (hardwares)
    *
    * @param $ID                    ID of the item to get
    * @param $purecontent  boolean  true : nothing change / false : convert to HTML display
    *
    * @return true if succeed else false
   **/
   function getFromDBwithData($ID, $purecontent) {

      if ($this->getFromDB($ID)) {
         if (!$purecontent) {
            $this->fields["content"] = nl2br(preg_replace("/\r\n\r\n/", "\r\n",
                                             $this->fields["content"]));
         }
         $this->getAdditionalDatas();
         return true;
      }
      return false;
   }


   function getAdditionalDatas() {
   }


   function canAdminActors() {
      return false;
   }


   function canAssign() {
      return false;
   }


   /**
    * Does current user have right to solve the current item?
    *
    * @return boolean
   **/
   function canSolve() {

      return ((Session::haveRight(static::$rightname, UPDATE)
               || $this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
               || (isset($_SESSION["glpigroups"])
                   && $this->haveAGroup(CommonITILActor::ASSIGN, $_SESSION["glpigroups"])))
              && self::isAllowedStatus($this->fields['status'], self::SOLVED)
              // No edition on closed status
              && !in_array($this->fields['status'], $this->getClosedStatusArray()));
   }

   /**
    * Does current user have right to solve the current item; if it was not closed?
    *
    * @return boolean
   **/
   function maySolve() {

      return ((Session::haveRight(static::$rightname, UPDATE)
               || $this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
               || (isset($_SESSION["glpigroups"])
                   && $this->haveAGroup(CommonITILActor::ASSIGN, $_SESSION["glpigroups"])))
              && self::isAllowedStatus($this->fields['status'], self::SOLVED));
   }



   /**
    * Is a user linked to the object ?
    *
    * @param $type               type to search (see constants)
    * @param $users_id  integer  user ID
    *
    * @return boolean
   **/
   function isUser($type, $users_id) {

      if (isset($this->users[$type])) {
         foreach ($this->users[$type] as $data) {
            if ($data['users_id'] == $users_id) {
               return true;
            }
         }
      }

      return false;
   }


   /**
    * Is a group linked to the object ?
    *
    * @param $type               type to search (see constants)
    * @param $groups_id  integer group ID
    *
    * @return boolean
   **/
   function isGroup($type, $groups_id) {

      if (isset($this->groups[$type])) {
         foreach ($this->groups[$type] as $data) {
            if ($data['groups_id'] == $groups_id) {
               return true;
            }
         }
      }
      return false;
   }


   /**
    * Is a supplier linked to the object ?
    *
    * @since 0.84
    *
    * @param $type               type to search (see constants)
    * @param $suppliers_id  integer supplier ID
    *
    * @return boolean
   **/
   function isSupplier($type, $suppliers_id) {

      if (isset($this->suppliers[$type])) {
         foreach ($this->suppliers[$type] as $data) {
            if ($data['suppliers_id'] == $suppliers_id) {
               return true;
            }
         }
      }
      return false;
   }


   /**
    * get users linked to a object
    *
    * @param $type type to search (see constants)
    *
    * @return array
   **/
   function getUsers($type) {

      if (isset($this->users[$type])) {
         return $this->users[$type];
      }

      return [];
   }


   /**
    * get groups linked to a object
    *
    * @param $type type to search (see constants)
    *
    * @return array
   **/
   function getGroups($type) {

      if (isset($this->groups[$type])) {
         return $this->groups[$type];
      }

      return [];
   }


   /**
    * get users linked to an object including groups ones
    *
    * @since 0.85
    *
    * @param $type type to search (see constants)
    *
    * @return array
   **/
   function getAllUsers ($type) {

      $users = [];
      foreach ($this->getUsers($type) as $link) {
         $users[$link['users_id']] = $link['users_id'];
      }

      foreach ($this->getGroups($type) as $link) {
         $gusers = Group_User::getGroupUsers($link['groups_id']);
         foreach ($gusers as $user) {
            $users[$user['id']] = $user['id'];
         }
      }

      return $users;
   }


   /**
    * get suppliers linked to a object
    *
    * @since 0.84
    *
    * @param $type type to search (see constants)
    *
    * @return array
   **/
   function getSuppliers($type) {

      if (isset($this->suppliers[$type])) {
         return $this->suppliers[$type];
      }

      return [];
   }


   /**
    * count users linked to object by type or global
    *
    * @param $type type to search (see constants) / 0 for all (default 0)
    *
    * @return integer
   **/
   function countUsers($type = 0) {

      if ($type > 0) {
         if (isset($this->users[$type])) {
            return count($this->users[$type]);
         }

      } else {
         if (count($this->users)) {
            $count = 0;
            foreach ($this->users as $u) {
               $count += count($u);
            }
            return $count;
         }
      }
      return 0;
   }


   /**
    * count groups linked to object by type or global
    *
    * @param $type type to search (see constants) / 0 for all (default 0)
    *
    * @return integer
   **/
   function countGroups($type = 0) {

      if ($type > 0) {
         if (isset($this->groups[$type])) {
            return count($this->groups[$type]);
         }

      } else {
         if (count($this->groups)) {
            $count = 0;
            foreach ($this->groups as $u) {
               $count += count($u);
            }
            return $count;
         }
      }
      return 0;
   }


   /**
    * count suppliers linked to object by type or global
    *
    * @since 0.84
    *
    * @param $type type to search (see constants) / 0 for all (default 0)
    *
    * @return integer
   **/
   function countSuppliers($type = 0) {

      if ($type > 0) {
         if (isset($this->suppliers[$type])) {
            return count($this->suppliers[$type]);
         }

      } else {
         if (count($this->suppliers)) {
            $count = 0;
            foreach ($this->suppliers as $u) {
               $count += count($u);
            }
            return $count;
         }
      }
      return 0;
   }


   /**
    * Is one of groups linked to the object ?
    *
    * @param $type            type to search (see constants)
    * @param $groups  array   of group ID
    *
    * @return boolean
   **/
   function haveAGroup($type, array $groups) {

      if (is_array($groups) && count($groups)
          && isset($this->groups[$type])) {

         foreach ($groups as $groups_id) {
            foreach ($this->groups[$type] as $data) {
               if ($data['groups_id'] == $groups_id) {
                  return true;
               }
            }
         }
      }
      return false;
   }


   /**
    * Get Default actor when creating the object
    *
    * @param $type type to search (see constants)
    *
    * @return boolean
   **/
   function getDefaultActor($type) {

      /// TODO own_ticket -> own_itilobject
      if ($type == CommonITILActor::ASSIGN) {
         if (Session::haveRight("ticket", Ticket::OWN)) {
            return Session::getLoginUserID();
         }
      }
      return 0;
   }


   /**
    * Get Default actor when creating the object
    *
    * @param $type type to search (see constants)
    *
    * @return boolean
   **/
   function getDefaultActorRightSearch($type) {

      if ($type == CommonITILActor::ASSIGN) {
         return "own_ticket";
      }
      return "all";
   }


   /**
    * Count active ITIL Objects requested by a user
    *
    * @since 0.83
    *
    * @param $users_id integer ID of the User
    *
    * @return integer
   **/
   function countActiveObjectsForUser($users_id) {

      $linkclass = new $this->userlinkclass();
      $itemtable = $this->getTable();
      $itemtype  = $this->getType();
      $itemfk    = $this->getForeignKeyField();
      $linktable = $linkclass->getTable();

      return countElementsInTable([$itemtable,$linktable],
                                  getEntitiesRestrictRequest("", $itemtable)."
                                    AND `$linktable`.`$itemfk` = `$itemtable`.`id`
                                    AND `$linktable`.`users_id` = '$users_id'
                                    AND `$linktable`.`type` = '".CommonITILActor::REQUESTER."'
                                    AND `$itemtable`.`is_deleted` = 0
                                    AND `$itemtable`.`status`
                                       NOT IN ('".implode("', '",
                                                          array_merge($this->getSolvedStatusArray(),
                                                                      $this->getClosedStatusArray())
                                                          )."')");
   }


   /**
    * Count active ITIL Objects assigned to a user
    *
    * @since 0.83
    *
    * @param $users_id integer ID of the User
    *
    * @return integer
   **/
   function countActiveObjectsForTech($users_id) {

      $linkclass = new $this->userlinkclass();
      $itemtable = $this->getTable();
      $itemtype  = $this->getType();
      $itemfk    = $this->getForeignKeyField();
      $linktable = $linkclass->getTable();

      return countElementsInTable([$itemtable,$linktable],
                                  getEntitiesRestrictRequest("", $itemtable)."
                                    AND `$linktable`.`$itemfk` = `$itemtable`.`id`
                                    AND `$linktable`.`users_id` = '$users_id'
                                    AND `$linktable`.`type` = '".CommonITILActor::ASSIGN."'
                                    AND `$itemtable`.`is_deleted` = 0
                                    AND `$itemtable`.`status`
                                       NOT IN ('".implode("', '",
                                                          array_merge($this->getSolvedStatusArray(),
                                                                      $this->getClosedStatusArray())
                                                          )."')");
   }


   /**
    * Count active ITIL Objects assigned to a group
    *
    * @since 0.84
    *
    * @param $groups_id integer ID of the User
    *
    * @return integer
   **/
   function countActiveObjectsForTechGroup($groups_id) {

      $linkclass = new $this->grouplinkclass();
      $itemtable = $this->getTable();
      $itemtype  = $this->getType();
      $itemfk    = $this->getForeignKeyField();
      $linktable = $linkclass->getTable();

      return countElementsInTable([$itemtable,$linktable],
                                  getEntitiesRestrictRequest("", $itemtable)."
                                    AND `$linktable`.`$itemfk` = `$itemtable`.`id`
                                    AND `$linktable`.`groups_id` = '$groups_id'
                                    AND `$linktable`.`type` = '".CommonITILActor::ASSIGN."'
                                    AND `$itemtable`.`is_deleted` = 0
                                    AND `$itemtable`.`status`
                                       NOT IN ('".implode("', '",
                                                          array_merge($this->getSolvedStatusArray(),
                                                                      $this->getClosedStatusArray())
                                                          )."')");
   }


   /**
    * Count active ITIL Objects assigned to a supplier
    *
    * @since 0.85
    *
    * @param $suppliers_id integer ID of the Supplier
    *
    * @return integer
    **/
   function countActiveObjectsForSupplier($suppliers_id) {

      $linkclass = new $this->supplierlinkclass();
      $itemtable = $this->getTable();
      $itemtype  = $this->getType();
      $itemfk    = $this->getForeignKeyField();
      $linktable = $linkclass->getTable();

      return countElementsInTable([$itemtable,$linktable],
                                  getEntitiesRestrictRequest("", $itemtable)."
                                    AND `$linktable`.`$itemfk` = `$itemtable`.`id`
                                    AND `$linktable`.`suppliers_id` = '$suppliers_id'
                                    AND `$linktable`.`type` = '".CommonITILActor::ASSIGN."'
                                    AND `$itemtable`.`is_deleted` = 0
                                    AND `$itemtable`.`status`
                                    NOT IN ('".implode("', '",
                                                       array_merge($this->getSolvedStatusArray(),
                                                                   $this->getClosedStatusArray())
                                                      )."')");
   }


   function cleanDBonPurge() {

      if (!empty($this->grouplinkclass)) {
         $class = new $this->grouplinkclass();
         $class->cleanDBonItemDelete($this->getType(), $this->fields['id']);
      }

      if (!empty($this->userlinkclass)) {
         $class = new $this->userlinkclass();
         $class->cleanDBonItemDelete($this->getType(), $this->fields['id']);
      }

      if (!empty($this->supplierlinkclass)) {
         $class = new $this->supplierlinkclass();
         $class->cleanDBonItemDelete($this->getType(), $this->fields['id']);
      }

      $solution = new ITILSolution();
      $solution->removeForItem($this->getType(), $this->getID());
   }


   function prepareInputForUpdate($input) {

      // Add document if needed
      $this->getFromDB($input["id"]); // entities_id field required
      if (!isset($input['_donotadddocs']) || !$input['_donotadddocs']) {
         $options = [];
         if (isset($input['solution'])) {
            $options['content_field'] = 'solution';
         }
         $input = $this->addFiles($input, $options);
      }

      if (isset($input["document"]) && ($input["document"] > 0)) {
         $doc = new Document();
         if ($doc->getFromDB($input["document"])) {
            $docitem = new Document_Item();
            if ($docitem->add(['documents_id' => $input["document"],
                                    'itemtype'     => $this->getType(),
                                    'items_id'     => $input["id"]])) {
               // Force date_mod of tracking
               $input["date_mod"]     = $_SESSION["glpi_currenttime"];
               $input['_doc_added'][] = $doc->fields["name"];
            }
         }
         unset($input["document"]);
      }

      if (isset($input["date"]) && empty($input["date"])) {
         unset($input["date"]);
      }

      if (isset($input["closedate"]) && empty($input["closedate"])) {
         unset($input["closedate"]);
      }

      if (isset($input["solvedate"]) && empty($input["solvedate"])) {
         unset($input["solvedate"]);
      }

      if (isset($input['_itil_requester'])) {
         if (isset($input['_itil_requester']['_type'])) {
            $input['_itil_requester']['type']                      = CommonITILActor::REQUESTER;
            $input['_itil_requester'][$this->getForeignKeyField()] = $input['id'];

            switch ($input['_itil_requester']['_type']) {
               case "user" :
                  if (isset($input['_itil_requester']['use_notification'])
                      && is_array($input['_itil_requester']['use_notification'])) {
                     $input['_itil_requester']['use_notification'] = $input['_itil_requester']['use_notification'][0];
                  }
                  if (isset($input['_itil_requester']['alternative_email'])
                      && is_array($input['_itil_requester']['alternative_email'])) {
                     $input['_itil_requester']['alternative_email'] = $input['_itil_requester']['alternative_email'][0];
                  }

                  if (!empty($this->userlinkclass)) {
                     if (isset($input['_itil_requester']['alternative_email'])
                         && $input['_itil_requester']['alternative_email']
                         && !NotificationMailing::isUserAddressValid($input['_itil_requester']['alternative_email'])) {

                        $input['_itil_requester']['alternative_email'] = '';
                        Session::addMessageAfterRedirect(__('Invalid email address'), false, ERROR);
                     }

                     if ((isset($input['_itil_requester']['alternative_email'])
                          && $input['_itil_requester']['alternative_email'])
                         || ($input['_itil_requester']['users_id'] > 0)) {

                        $useractors = new $this->userlinkclass();
                        if (isset($input['_auto_update'])
                            || $useractors->can(-1, CREATE, $input['_itil_requester'])) {
                           $input['_itil_requester']['_from_object'] = true;
                           $useractors->add($input['_itil_requester']);
                           $input['_forcenotif']                     = true;
                        }
                     }
                  }
                  break;

               case "group" :
                  if (!empty($this->grouplinkclass)
                      && ($input['_itil_requester']['groups_id'] > 0)) {
                     $groupactors = new $this->grouplinkclass();
                     if (isset($input['_auto_update'])
                         || $groupactors->can(-1, CREATE, $input['_itil_requester'])) {
                        $input['_itil_requester']['_from_object'] = true;
                        $groupactors->add($input['_itil_requester']);
                        $input['_forcenotif']                     = true;
                     }
                  }
                  break;
            }
         }
      }

      if (isset($input['_itil_observer'])) {
         if (isset($input['_itil_observer']['_type'])) {
            $input['_itil_observer']['type']                      = CommonITILActor::OBSERVER;
            $input['_itil_observer'][$this->getForeignKeyField()] = $input['id'];

            switch ($input['_itil_observer']['_type']) {
               case "user" :
                  if (isset($input['_itil_observer']['use_notification'])
                      && is_array($input['_itil_observer']['use_notification'])) {
                     $input['_itil_observer']['use_notification'] = $input['_itil_observer']['use_notification'][0];
                  }
                  if (isset($input['_itil_observer']['alternative_email'])
                      && is_array($input['_itil_observer']['alternative_email'])) {
                     $input['_itil_observer']['alternative_email'] = $input['_itil_observer']['alternative_email'][0];
                  }

                  if (!empty($this->userlinkclass)) {
                     if (isset($input['_itil_observer']['alternative_email'])
                         && $input['_itil_observer']['alternative_email']
                         && !NotificationMailing::isUserAddressValid($input['_itil_observer']['alternative_email'])) {

                        $input['_itil_observer']['alternative_email'] = '';
                        Session::addMessageAfterRedirect(__('Invalid email address'), false, ERROR);
                     }
                     if ((isset($input['_itil_observer']['alternative_email'])
                          && $input['_itil_observer']['alternative_email'])
                         || ($input['_itil_observer']['users_id'] > 0)) {
                        $useractors = new $this->userlinkclass();
                        if (isset($input['_auto_update'])
                           || $useractors->can(-1, CREATE, $input['_itil_observer'])) {
                           $input['_itil_observer']['_from_object'] = true;
                           $useractors->add($input['_itil_observer']);
                           $input['_forcenotif']                    = true;
                        }
                     }
                  }
                  break;

               case "group" :
                  if (!empty($this->grouplinkclass)
                       && ($input['_itil_observer']['groups_id'] > 0)) {
                     $groupactors = new $this->grouplinkclass();
                     if (isset($input['_auto_update'])
                         || $groupactors->can(-1, CREATE, $input['_itil_observer'])) {
                        $input['_itil_observer']['_from_object'] = true;
                        $groupactors->add($input['_itil_observer']);
                        $input['_forcenotif']                    = true;
                     }
                  }
                  break;
            }
         }
      }

      if (isset($input['_itil_assign'])) {
         if (isset($input['_itil_assign']['_type'])) {
            $input['_itil_assign']['type']                      = CommonITILActor::ASSIGN;
            $input['_itil_assign'][$this->getForeignKeyField()] = $input['id'];

            if (isset($input['_itil_assign']['use_notification'])
                  && is_array($input['_itil_assign']['use_notification'])) {
               $input['_itil_assign']['use_notification'] = $input['_itil_assign']['use_notification'][0];
            }
            if (isset($input['_itil_assign']['alternative_email'])
                  && is_array($input['_itil_assign']['alternative_email'])) {
               $input['_itil_assign']['alternative_email'] = $input['_itil_assign']['alternative_email'][0];
            }

            switch ($input['_itil_assign']['_type']) {
               case "user" :
                  if (!empty($this->userlinkclass)
                      && ($input['_itil_assign']['users_id'] > 0)) {
                     $useractors = new $this->userlinkclass();
                     if (isset($input['_auto_update'])
                         || $useractors->can(-1, CREATE, $input['_itil_assign'])) {
                        $input['_itil_assign']['_from_object'] = true;
                        $useractors->add($input['_itil_assign']);
                        $input['_forcenotif']                  = true;
                        if ((!isset($input['status'])
                             && in_array($this->fields['status'], $this->getNewStatusArray()))
                            || (isset($input['status'])
                                && in_array($input['status'], $this->getNewStatusArray()))) {
                           if (in_array(self::ASSIGNED, array_keys($this->getAllStatusArray()))) {
                              $input['status'] = self::ASSIGNED;
                           }
                        }
                     }
                  }
                  break;

               case "group" :
                  if (!empty($this->grouplinkclass)
                      && ($input['_itil_assign']['groups_id'] > 0)) {
                     $groupactors = new $this->grouplinkclass();

                     if (isset($input['_auto_update'])
                         || $groupactors->can(-1, CREATE, $input['_itil_assign'])) {
                        $input['_itil_assign']['_from_object'] = true;
                        $groupactors->add($input['_itil_assign']);
                        $input['_forcenotif']                  = true;
                        if ((!isset($input['status'])
                             && (in_array($this->fields['status'], $this->getNewStatusArray())))
                            || (isset($input['status'])
                                && (in_array($input['status'], $this->getNewStatusArray())))) {
                           if (in_array(self::ASSIGNED, array_keys($this->getAllStatusArray()))) {
                              $input['status'] = self::ASSIGNED;
                           }
                        }
                     }
                  }
                  break;

               case "supplier" :
                  if (!empty($this->supplierlinkclass)
                      && ($input['_itil_assign']['suppliers_id'] > 0)) {
                     $supplieractors = new $this->supplierlinkclass();
                     if (isset($input['_auto_update'])
                         || $supplieractors->can(-1, CREATE, $input['_itil_assign'])) {
                        $input['_itil_assign']['_from_object'] = true;
                        $supplieractors->add($input['_itil_assign']);
                        $input['_forcenotif']                  = true;
                        if ((!isset($input['status'])
                             && (in_array($this->fields['status'], $this->getNewStatusArray())))
                            || (isset($input['status'])
                                && (in_array($input['status'], $this->getNewStatusArray())))) {
                           if (in_array(self::ASSIGNED, array_keys($this->getAllStatusArray()))) {
                              $input['status'] = self::ASSIGNED;
                           }

                        }
                     }
                  }
                  break;
            }
         }
      }

      $this->addAdditionalActors($input);

      // set last updater if interactive user
      if (!Session::isCron()) {
         $input['users_id_lastupdater'] = Session::getLoginUserID();
      }

      $solvedclosed = array_merge(
         $this->getSolvedStatusArray(),
         $this->getClosedStatusArray()
      );

      if (isset($input["status"])
          && !in_array($input["status"], $solvedclosed)) {
         $input['solvedate'] = 'NULL';
      }

      if (isset($input["status"]) && !in_array($input["status"], $this->getClosedStatusArray())) {
         $input['closedate'] = 'NULL';
      }

      // Setting a solution type means the ticket is solved
      if (isset($input["solutiontypes_id"])
          && (!isset($input['status']) || !in_array($input["status"], $solvedclosed))) {
         $solution = new ITILSolution();
         $soltype = new SolutionType();
         $soltype->getFromDB($input['solutiontypes_id']);
         $solution->add([
            'itemtype'           => $this->getType(),
            'items_id'           => $this->getID(),
            'solutiontypes_id'   => $input['solutiontypes_id'],
            'content'            => 'Solved using type ' . $soltype->getName()
         ]);
      }

      return $input;
   }


   function pre_updateInDB() {
      global $DB;

      // get again object to reload actors
      $this->loadActors();

      // Check dates change interval due to the fact that second are not displayed in form
      if ((($key = array_search('date', $this->updates)) !== false)
          && (substr($this->fields["date"], 0, 16) == substr($this->oldvalues['date'], 0, 16))) {
         unset($this->updates[$key]);
         unset($this->oldvalues['date']);
      }

      if ((($key=array_search('closedate', $this->updates)) !== false)
          && (substr($this->fields["closedate"], 0, 16) == substr($this->oldvalues['closedate'], 0, 16))) {
         unset($this->updates[$key]);
         unset($this->oldvalues['closedate']);
      }

      if ((($key=array_search('time_to_resolve', $this->updates)) !== false)
          && (substr($this->fields["time_to_resolve"], 0, 16) == substr($this->oldvalues['time_to_resolve'], 0, 16))) {
         unset($this->updates[$key]);
         unset($this->oldvalues['time_to_resolve']);
      }

      if ((($key=array_search('solvedate', $this->updates)) !== false)
          && (substr($this->fields["solvedate"], 0, 16) == substr($this->oldvalues['solvedate'], 0, 16))) {
         unset($this->updates[$key]);
         unset($this->oldvalues['solvedate']);
      }

      if (isset($this->input["status"])) {
         if (($this->input["status"] != self::WAITING)
             && ($this->countSuppliers(CommonITILActor::ASSIGN) == 0)
             && ($this->countUsers(CommonITILActor::ASSIGN) == 0)
             && ($this->countGroups(CommonITILActor::ASSIGN) == 0)
             && !in_array($this->fields['status'], array_merge($this->getSolvedStatusArray(),
                                                              $this->getClosedStatusArray()))) {

            if (!in_array('status', $this->updates)) {
               $this->oldvalues['status'] = $this->fields['status'];
               $this->updates[] = 'status';
            }

            // $this->fields['status'] = self::INCOMING;
            // Don't change status if it's a new status allow
            if (in_array($this->oldvalues['status'], $this->getNewStatusArray())
                && !in_array($this->input['status'], $this->getNewStatusArray())) {
               $this->fields['status'] = $this->oldvalues['status'];
            }
         }

         if (in_array("status", $this->updates)
             && in_array($this->input["status"], $this->getSolvedStatusArray())) {
            $this->updates[]              = "solvedate";
            $this->oldvalues['solvedate'] = $this->fields["solvedate"];
            $this->fields["solvedate"]    = $_SESSION["glpi_currenttime"];
            // If invalid date : set open date
            if ($this->fields["solvedate"] < $this->fields["date"]) {
               $this->fields["solvedate"] = $this->fields["date"];
            }
         }

         if (in_array("status", $this->updates)
             && in_array($this->input["status"], $this->getClosedStatusArray())) {
            $this->updates[]              = "closedate";
            $this->oldvalues['closedate'] = $this->fields["closedate"];
            $this->fields["closedate"]    = $_SESSION["glpi_currenttime"];
            // If invalid date : set open date
            if ($this->fields["closedate"] < $this->fields["date"]) {
               $this->fields["closedate"] = $this->fields["date"];
            }
            // Set solvedate to closedate
            if (empty($this->fields["solvedate"])) {
               $this->updates[]              = "solvedate";
               $this->oldvalues['solvedate'] = $this->fields["solvedate"];
               $this->fields["solvedate"]    = $this->fields["closedate"];
            }
         }

      }

      // check dates

      // check time_to_resolve (SLA)
      if ((in_array("date", $this->updates) || in_array("time_to_resolve", $this->updates))
          && !is_null($this->fields["time_to_resolve"])) { // Date set

         if ($this->fields["time_to_resolve"] < $this->fields["date"]) {
            Session::addMessageAfterRedirect(__('Invalid dates. Update cancelled.'), false, ERROR);

            if (($key = array_search('date', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['date']);
            }
            if (($key = array_search('time_to_resolve', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['time_to_resolve']);
            }
         }
      }

      // check internal_time_to_resolve (OLA)
      if ((in_array("date", $this->updates) || in_array("internal_time_to_resolve", $this->updates))
          && !is_null($this->fields["internal_time_to_resolve"])) { // Date set

         if ($this->fields["internal_time_to_resolve"] < $this->fields["date"]) {
            Session::addMessageAfterRedirect(__('Invalid dates. Update cancelled.'), false, ERROR);

            if (($key = array_search('date', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['date']);
            }
            if (($key = array_search('internal_time_to_resolve', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['internal_time_to_resolve']);
            }
         }
      }

      // Status close : check dates
      if (in_array($this->fields["status"], $this->getClosedStatusArray())
          && (in_array("date", $this->updates) || in_array("closedate", $this->updates))) {

         // Invalid dates : no change
         // closedate must be > solvedate
         if ($this->fields["closedate"] < $this->fields["solvedate"]) {
            Session::addMessageAfterRedirect(__('Invalid dates. Update cancelled.'), false, ERROR);

            if (($key = array_search('closedate', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['closedate']);
            }
         }

         // closedate must be > create date
         if ($this->fields["closedate"] < $this->fields["date"]) {
            Session::addMessageAfterRedirect(__('Invalid dates. Update cancelled.'), false, ERROR);
            if (($key = array_search('date', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['date']);
            }
            if (($key = array_search('closedate', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['closedate']);
            }
         }
      }

      if ((($key = array_search('status', $this->updates)) !== false)
          && $this->oldvalues['status'] == $this->fields['status']) {

         unset($this->updates[$key]);
         unset($this->oldvalues['status']);
      }

      // Status solved : check dates
      if (in_array($this->fields["status"], $this->getSolvedStatusArray())
          && (in_array("date", $this->updates) || in_array("solvedate", $this->updates))) {

         // Invalid dates : no change
         // solvedate must be > create date
         if ($this->fields["solvedate"] < $this->fields["date"]) {
            Session::addMessageAfterRedirect(__('Invalid dates. Update cancelled.'), false, ERROR);

            if (($key = array_search('date', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['date']);
            }
            if (($key = array_search('solvedate', $this->updates)) !== false) {
               unset($this->updates[$key]);
               unset($this->oldvalues['solvedate']);
            }
         }
      }

      // Manage come back to waiting state
      if (!is_null($this->fields['begin_waiting_date'])
          && (($key = array_search('status', $this->updates)) !== false)
          && (($this->oldvalues['status'] == self::WAITING)
               // From solved to another state than closed
              || (in_array($this->oldvalues["status"], $this->getSolvedStatusArray())
                  && !in_array($this->fields["status"], $this->getClosedStatusArray())))) {

         // Compute ticket waiting time use calendar if exists
         $calendar     = new Calendar();
         $calendars_id = $this->getCalendar();
         $delay_time   = 0;

         // Compute ticket waiting time use calendar if exists
         // Using calendar
         if (($calendars_id > 0)
             && $calendar->getFromDB($calendars_id)) {
            $delay_time = $calendar->getActiveTimeBetween($this->fields['begin_waiting_date'],
                                                          $_SESSION["glpi_currenttime"]);
         } else { // Not calendar defined
            $delay_time = strtotime($_SESSION["glpi_currenttime"])
                           -strtotime($this->fields['begin_waiting_date']);
         }

         // SLA case : compute sla_ttr duration
         if (isset($this->fields['slas_ttr_id']) && ($this->fields['slas_ttr_id'] > 0)) {
            $sla = new SLA();
            if ($sla->getFromDB($this->fields['slas_ttr_id'])) {
               $sla->setTicketCalendar($calendars_id);
               $delay_time_sla  = $sla->getActiveTimeBetween($this->fields['begin_waiting_date'],
                                                             $_SESSION["glpi_currenttime"]);
               $this->updates[] = "sla_waiting_duration";
               $this->fields["sla_waiting_duration"] += $delay_time_sla;
            }

            // Compute new time_to_resolve
            $this->updates[]                 = "time_to_resolve";
            $this->fields['time_to_resolve'] = $sla->computeDate($this->fields['date'],
                                                                 $this->fields["sla_waiting_duration"]);
            // Add current level to do
            $sla->addLevelToDo($this);

         } else {
            // Using calendar
            if (($calendars_id > 0)
                && $calendar->getFromDB($calendars_id)) {
               if ($this->fields['time_to_resolve'] > 0) {
                  // compute new due date using calendar
                  $this->updates[]                 = "time_to_resolve";
                  $this->fields['time_to_resolve'] = $calendar->computeEndDate($this->fields['time_to_resolve'],
                                                                               $delay_time);
               }

            } else { // Not calendar defined
               if ($this->fields['time_to_resolve'] > 0) {
                  // compute new due date : no calendar so add computed delay_time
                  $this->updates[]                 = "time_to_resolve";
                  $this->fields['time_to_resolve'] = date('Y-m-d H:i:s',
                                                          $delay_time + strtotime($this->fields['time_to_resolve']));
               }
            }
         }

         // OLA case : compute ola_ttr duration
         if (isset($this->fields['olas_ttr_id']) && ($this->fields['olas_ttr_id'] > 0)) {
            $ola = new OLA();
            if ($ola->getFromDB($this->fields['olas_ttr_id'])) {
               $ola->setTicketCalendar($calendars_id);
               $delay_time_ola  = $ola->getActiveTimeBetween($this->fields['begin_waiting_date'],
                                                             $_SESSION["glpi_currenttime"]);
               $this->updates[]                      = "ola_waiting_duration";
               $this->fields["ola_waiting_duration"] += $delay_time_ola;
            }

            // Compute new internal_time_to_resolve
            $this->updates[]                          = "internal_time_to_resolve";
            $this->fields['internal_time_to_resolve'] = $ola->computeDate($this->fields['date'],
                                                                          $this->fields["ola_waiting_duration"]);
            // Add current level to do
            $ola->addLevelToDo($this, $this->fields["ttr_olalevels_id"]);

         } else {
            // Using calendar
            if (($calendars_id > 0)
                && $calendar->getFromDB($calendars_id)) {
               if ($this->fields['internal_time_to_resolve'] > 0) {
                  // compute new internal_time_to_resolve using calendar
                  $this->updates[]                          = "internal_time_to_resolve";
                  $this->fields['internal_time_to_resolve'] = $calendar->computeEndDate(
                                                                              $this->fields['internal_time_to_resolve'],
                                                                              $delay_time);
               }

            } else { // Not calendar defined
               if ($this->fields['internal_time_to_resolve'] > 0) {
                  // compute new internal_time_to_resolve : no calendar so add computed delay_time
                  $this->updates[]                          = "internal_time_to_resolve";
                  $this->fields['internal_time_to_resolve'] = date('Y-m-d H:i:s',
                                                                   $delay_time +
                                                                   strtotime($this->fields['internal_time_to_resolve']));
               }
            }
         }

         $this->updates[]                   = "waiting_duration";
         $this->fields["waiting_duration"] += $delay_time;

         // Reset begin_waiting_date
         $this->updates[]                    = "begin_waiting_date";
         $this->fields["begin_waiting_date"] = 'NULL';
      }

      // Set begin waiting date if needed
      if ((($key = array_search('status', $this->updates)) !== false)
          && (($this->fields['status'] == self::WAITING)
              || in_array($this->fields["status"], $this->getSolvedStatusArray()))) {

         $this->updates[]                    = "begin_waiting_date";
         $this->fields["begin_waiting_date"] = $_SESSION["glpi_currenttime"];

         // Specific for tickets
         if (isset($this->fields['slas_ttr_id']) && ($this->fields['slas_ttr_id'] > 0)) {
            SLA::deleteLevelsToDo($this);
         }

         if (isset($this->fields['olas_ttr_id']) && ($this->fields['olas_ttr_id'] > 0)) {
            OLA::deleteLevelsToDo($this);
         }
      }

      // solve_delay_stat : use delay between opendate and solvedate
      if (in_array("solvedate", $this->updates)) {
         $this->updates[]                  = "solve_delay_stat";
         $this->fields['solve_delay_stat'] = $this->computeSolveDelayStat();
      }
      // close_delay_stat : use delay between opendate and closedate
      if (in_array("closedate", $this->updates)) {
         $this->updates[]                  = "close_delay_stat";
         $this->fields['close_delay_stat'] = $this->computeCloseDelayStat();
      }

      //Look for reopening
      $statuses = array_merge(
         $this->getSolvedStatusArray(),
         $this->getClosedStatusArray()
      );
      if (($key = array_search('status', $this->updates)) !== false
         && in_array($this->oldvalues['status'], $statuses)
         && !in_array($this->fields['status'], $statuses)
      ) {
         //Mark existing solutions as refused
         //needs DB::update() to support order and limit to get migrated
         $query = "UPDATE `" . ITILSolution::getTable() . "`
            SET `status`=".CommonITILValidation::REFUSED.", `users_id_approval`=" . Session::getLoginUserID() . ",
            `date_approval`='". date('Y-m-d H:i:s') ."'
               WHERE `itemtype`='" . static::getType() . "'
               AND `items_id`=" . $this->getID() . "
               ORDER BY date_creation DESC, id DESC LIMIT 1";
         $DB->query($query);
      }

      if (isset($this->input['_accepted'])) {
         //Mark last solution as approved
         //needs DB::update() to support order and limit to get migrated
         $query = "UPDATE `" . ITILSolution::getTable() . "`
            SET `status`=".CommonITILValidation::ACCEPTED.", `users_id_approval`=" . Session::getLoginUserID() . ",
            `date_approval`='". date('Y-m-d H:i:s') ."'
               WHERE `itemtype`='" . static::getType() . "'
               AND `items_id`=" . $this->getID() . "
               ORDER BY date_creation DESC, id DESC LIMIT 1";
         $DB->query($query);
      }

      // Do not take into account date_mod if no update is done
      if ((count($this->updates) == 1)
          && (($key = array_search('date_mod', $this->updates)) !== false)) {
         unset($this->updates[$key]);
      }
   }


   function prepareInputForAdd($input) {
      global $CFG_GLPI;

      // Set default status to avoid notice
      if (!isset($input["status"])) {
         $input["status"] = self::INCOMING;
      }

      if (!isset($input["urgency"])
          || !($CFG_GLPI['urgency_mask']&(1<<$input["urgency"]))) {
         $input["urgency"] = 3;
      }
      if (!isset($input["impact"])
          || !($CFG_GLPI['impact_mask']&(1<<$input["impact"]))) {
         $input["impact"] = 3;
      }

      $canpriority = true;
      if ($this->getType() == 'Ticket') {
         $canpriority = Session::haveRight(Ticket::$rightname, Ticket::CHANGEPRIORITY);
      }

      if ($canpriority && !isset($input["priority"]) || !$canpriority) {
         $input["priority"] = $this->computePriority($input["urgency"], $input["impact"]);
      }

      // set last updater if interactive user
      if (!Session::isCron() && ($last_updater = Session::getLoginUserID(true))) {
         $input['users_id_lastupdater'] = $last_updater;
      }

      // No Auto set Import for external source
      if (!isset($input['_auto_import'])) {
         if (!isset($input["_users_id_requester"])) {
            if ($uid = Session::getLoginUserID()) {
               $input["_users_id_requester"] = $uid;
            }
         }
      }

      // No Auto set Import for external source
      if (($uid = Session::getLoginUserID())
          && !isset($input['_auto_import'])) {
         $input["users_id_recipient"] = $uid;
      } else if (isset($input["_users_id_requester"]) && $input["_users_id_requester"]
                 && !isset($input["users_id_recipient"])) {
         if (!is_array($input['_users_id_requester'])) {
            $input["users_id_recipient"] = $input["_users_id_requester"];
         }
      }

      // No name set name
      $input["name"]    = ltrim($input["name"]);
      $input['content'] = ltrim($input['content']);
      if (empty($input["name"])) {
         $input['name'] = Html::clean(Html::entity_decode_deep($input['content']));
         $input["name"] = preg_replace('/\\r\\n/', ' ', $input['name']);
         $input["name"] = preg_replace('/\\n/', ' ', $input['name']);
         // For mailcollector
         $input["name"] = preg_replace('/\\\\r\\\\n/', ' ', $input['name']);
         $input["name"] = preg_replace('/\\\\n/', ' ', $input['name']);
         $input['name'] = Toolbox::stripslashes_deep($input['name']);
         $input["name"] = Toolbox::substr($input['name'], 0, 70);
         $input['name'] = Toolbox::addslashes_deep($input['name']);
      }

      // Set default dropdown
      $dropdown_fields = ['entities_id', 'itilcategories_id'];
      foreach ($dropdown_fields as $field) {
         if (!isset($input[$field])) {
            $input[$field] = 0;
         }
      }

      $input = $this->computeDefaultValuesForAdd($input);

      return $input;
   }

   /**
    * Compute default values for Add
    * (to be passed in prepareInputForAdd before and after rules if needed)
    *
    * @since 0.84
    *
    * @param $input
    *
    * @return string
   **/
   function computeDefaultValuesForAdd($input) {

      if (!isset($input["status"])) {
         $input["status"] = self::INCOMING;
      }

      if (!isset($input["date"]) || empty($input["date"])) {
         $input["date"] = $_SESSION["glpi_currenttime"];
      }

      if (isset($input["status"]) && in_array($input["status"], $this->getSolvedStatusArray())) {
         if (isset($input["date"])) {
            $input["solvedate"] = $input["date"];
         } else {
            $input["solvedate"] = $_SESSION["glpi_currenttime"];
         }
      }

      if (isset($input["status"]) && in_array($input["status"], $this->getClosedStatusArray())) {
         if (isset($input["date"])) {
            $input["closedate"] = $input["date"];
         } else {
            $input["closedate"] = $_SESSION["glpi_currenttime"];
         }
         $input['solvedate'] = $input["closedate"];
      }

      // Set begin waiting time if status is waiting
      if (isset($input["status"]) && ($input["status"] == self::WAITING)) {
         $input['begin_waiting_date'] = $input['date'];
      }

      return $input;
   }


   function post_addItem() {

      // Add document if needed, without notification
      $this->input = $this->addFiles($this->input, ['force_update' => true]);

      // Add default document if set in template
      if (isset($this->input['_documents_id'])
          && is_array($this->input['_documents_id'])
          && count($this->input['_documents_id'])) {
         $docitem = new Document_Item();
         foreach ($this->input['_documents_id'] as $docID) {
            $docitem->add(['documents_id' => $docID,
                                '_do_notif'    => false,
                                'itemtype'     => $this->getType(),
                                'items_id'     => $this->fields['id']]);
         }
      }

      $useractors = null;
      // Add user groups linked to ITIL objects
      if (!empty($this->userlinkclass)) {
         $useractors = new $this->userlinkclass();
      }
      $groupactors = null;
      if (!empty($this->grouplinkclass)) {
         $groupactors = new $this->grouplinkclass();
      }
      $supplieractors = null;
      if (!empty($this->supplierlinkclass)) {
         $supplieractors = new $this->supplierlinkclass();
      }

      if (!is_null($useractors)) {
         if (isset($this->input["_users_id_requester"])) {

            if (is_array($this->input["_users_id_requester"])) {
               $tab_requester = $this->input["_users_id_requester"];
            } else {
               $tab_requester   = [];
               $tab_requester[] = $this->input["_users_id_requester"];
            }

            $requesterToAdd = [];
            foreach ($tab_requester as $key_requester => $requester) {
               if (in_array($requester, $requesterToAdd)) {
                  // This requester ID is already added;
                  continue;
               }

               $input2 = [$useractors->getItilObjectForeignKey() => $this->fields['id'],
                              'users_id'                              => $requester,
                              'type'                                  => CommonITILActor::REQUESTER];

               if (isset($this->input["_users_id_requester_notif"])) {
                  foreach ($this->input["_users_id_requester_notif"] as $key => $val) {
                     if (isset($val[$key_requester])) {
                        $input2[$key] = $val[$key_requester];
                     }
                  }
               }

               //empty actor
               if ($input2['users_id'] == 0
                   && (!isset($input2['alternative_email'])
                       || empty($input2['alternative_email']))) {
                  continue;
               } else if ($requester != 0) {
                  $requesterToAdd[] = $requester;
               }

               $input2['_from_object'] = true;
               $useractors->add($input2);
            }
         }

         if (isset($this->input["_users_id_observer"])) {

            if (is_array($this->input["_users_id_observer"])) {
               $tab_observer = $this->input["_users_id_observer"];
            } else {
               $tab_observer   = [];
               $tab_observer[] = $this->input["_users_id_observer"];
            }

            $observerToAdd = [];
            foreach ($tab_observer as $key_observer => $observer) {
               if (in_array($observer, $observerToAdd)) {
                  // This observer ID is already added;
                  continue;
               }

               $input2 = [$useractors->getItilObjectForeignKey() => $this->fields['id'],
                              'users_id'                              => $observer,
                              'type'                                  => CommonITILActor::OBSERVER];

               if (isset($this->input["_users_id_observer_notif"])) {
                  foreach ($this->input["_users_id_observer_notif"] as $key => $val) {
                     if (isset($val[$key_observer])) {
                        $input2[$key] = $val[$key_observer];
                     }
                  }
               }

               //empty actor
               if ($input2['users_id'] == 0
                   && (!isset($input2['alternative_email'])
                       || empty($input2['alternative_email']))) {
                  continue;
               } else if ($observer != 0) {
                  $observerToAdd[] = $observer;
               }

               $input2['_from_object'] = true;
               $useractors->add($input2);
            }
         }

         if (isset($this->input["_users_id_assign"])) {

            if (is_array($this->input["_users_id_assign"])) {
               $tab_assign = $this->input["_users_id_assign"];
            } else {
               $tab_assign   = [];
               $tab_assign[] = $this->input["_users_id_assign"];
            }

            $assignToAdd = [];
            foreach ($tab_assign as $key_assign => $assign) {
               if (in_array($assign, $assignToAdd)) {
                  // This assigned user ID is already added;
                  continue;
               }

               $input2 = [$useractors->getItilObjectForeignKey() => $this->fields['id'],
                              'users_id'                              => $assign,
                              'type'                                  => CommonITILActor::ASSIGN];

               if (isset($this->input["_users_id_assign_notif"])) {
                  foreach ($this->input["_users_id_assign_notif"] as $key => $val) {
                     if (isset($val[$key_assign])) {
                        $input2[$key] = $val[$key_assign];
                     }
                  }
               }

               //empty actor
               if ($input2['users_id'] == 0
                   && (!isset($input2['alternative_email'])
                       || empty($input2['alternative_email']))) {
                  continue;
               } else if ($assign != 0) {
                  $assignToAdd[] = $assign;
               }

               $input2['_from_object'] = true;
               $useractors->add($input2);
            }
         }
      }

      if (!is_null($groupactors)) {
         if (isset($this->input["_groups_id_requester"])) {
            $groups_id_requester = $this->input["_groups_id_requester"];
            if (!is_array($this->input["_groups_id_requester"])) {
               $groups_id_requester = [$this->input["_groups_id_requester"]];
            } else {
               $groups_id_requester = $this->input["_groups_id_requester"];
            }
            foreach ($groups_id_requester as $groups_id) {
               if ($groups_id > 0) {
                  $groupactors->add([$groupactors->getItilObjectForeignKey()
                        => $this->fields['id'],
                        'groups_id'    => $groups_id,
                        'type'         => CommonITILActor::REQUESTER,
                        '_from_object' => true]);
               }
            }
         }

         if (isset($this->input["_groups_id_assign"])) {
            if (!is_array($this->input["_groups_id_assign"])) {
               $groups_id_assign = [$this->input["_groups_id_assign"]];
            } else {
               $groups_id_assign = $this->input["_groups_id_assign"];
            }
            foreach ($groups_id_assign as $groups_id) {
               if ($groups_id > 0) {
                  $groupactors->add([$groupactors->getItilObjectForeignKey()
                        => $this->fields['id'],
                        'groups_id'    => $groups_id,
                        'type'         => CommonITILActor::ASSIGN,
                        '_from_object' => true]);
               }
            }
         }

         if (isset($this->input["_groups_id_observer"])) {
            if (!is_array($this->input["_groups_id_observer"])) {
               $groups_id_observer = [$this->input["_groups_id_observer"]];
            } else {
               $groups_id_observer = $this->input["_groups_id_observer"];
            }
            foreach ($groups_id_observer as $groups_id) {
               if ($groups_id > 0) {
                  $groupactors->add([$groupactors->getItilObjectForeignKey()
                                                         => $this->fields['id'],
                                          'groups_id'    => $groups_id,
                                          'type'         => CommonITILActor::OBSERVER,
                                          '_from_object' => true]);
               }
            }
         }
      }

      if (!is_null($supplieractors)) {
         if (isset($this->input["_suppliers_id_assign"])
             && ($this->input["_suppliers_id_assign"] > 0)) {

            if (is_array($this->input["_suppliers_id_assign"])) {
               $tab_assign = $this->input["_suppliers_id_assign"];
            } else {
               $tab_assign   = [];
               $tab_assign[] = $this->input["_suppliers_id_assign"];
            }

            $supplierToAdd = [];
            foreach ($tab_assign as $key_assign => $assign) {
               if (in_array($assign, $supplierToAdd)) {
                  // This assigned supplier ID is already added;
                  continue;
               }
               $input3 = [$supplieractors->getItilObjectForeignKey()
                                              => $this->fields['id'],
                               'suppliers_id' => $assign,
                               'type'         => CommonITILActor::ASSIGN];

               if (isset($this->input["_suppliers_id_assign_notif"])) {
                  foreach ($this->input["_suppliers_id_assign_notif"] as $key => $val) {
                     $input3[$key] = $val[$key_assign];
                  }
               }

               //empty supplier
               if ($input3['suppliers_id'] == 0
                   && (!isset($input3['alternative_email'])
                       || empty($input3['alternative_email']))) {
                  continue;
               } else if ($assign != 0) {
                  $supplierToAdd[] = $assign;
               }

               $input3['_from_object'] = true;
               $supplieractors->add($input3);
            }
         }
      }

      // Additional actors
      $this->addAdditionalActors($this->input);

   }


   /**
    * @since 0.84
    * @since 0.85 must have param $input
   **/
   private function addAdditionalActors($input) {

      $useractors = null;
      // Add user groups linked to ITIL objects
      if (!empty($this->userlinkclass)) {
         $useractors = new $this->userlinkclass();
      }
      $groupactors = null;
      if (!empty($this->grouplinkclass)) {
         $groupactors = new $this->grouplinkclass();
      }
      $supplieractors = null;
      if (!empty($this->supplierlinkclass)) {
         $supplieractors = new $this->supplierlinkclass();
      }

      // Additional groups actors
      if (!is_null($groupactors)) {
         // Requesters
         if (isset($input['_additional_groups_requesters'])
             && is_array($input['_additional_groups_requesters'])
             && count($input['_additional_groups_requesters'])) {

            $input2 = [$groupactors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                  => CommonITILActor::REQUESTER];

            foreach ($input['_additional_groups_requesters'] as $tmp) {
               if ($tmp > 0) {
                  $input2['groups_id']    = $tmp;
                  $input2['_from_object'] = true;
                  $groupactors->add($input2);
               }
            }
         }

         // Observers
         if (isset($input['_additional_groups_observers'])
             && is_array($input['_additional_groups_observers'])
             && count($input['_additional_groups_observers'])) {

            $input2 = [$groupactors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                  => CommonITILActor::OBSERVER];

            foreach ($input['_additional_groups_observers'] as $tmp) {
               if ($tmp > 0) {
                  $input2['groups_id']    = $tmp;
                  $input2['_from_object'] = true;
                  $groupactors->add($input2);
               }
            }
         }

         // Assigns
         if (isset($input['_additional_groups_assigns'])
             && is_array($input['_additional_groups_assigns'])
             && count($input['_additional_groups_assigns'])) {

            $input2 = [$groupactors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                  => CommonITILActor::ASSIGN];

            foreach ($input['_additional_groups_assigns'] as $tmp) {
               if ($tmp > 0) {
                  $input2['groups_id']    = $tmp;
                  $input2['_from_object'] = true;
                  $groupactors->add($input2);
               }
            }
         }
      }

      // Additional suppliers actors
      if (!is_null($supplieractors)) {
         // Assigns
         if (isset($input['_additional_suppliers_assigns'])
             && is_array($input['_additional_suppliers_assigns'])
             && count($input['_additional_suppliers_assigns'])) {

            $input2 = [$supplieractors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                     => CommonITILActor::ASSIGN];

            foreach ($input['_additional_suppliers_assigns'] as $tmp) {
               if ($tmp > 0) {
                  $input2['suppliers_id'] = $tmp;
                  $input2['_from_object'] = true;
                  $supplieractors->add($input2);
               }
            }
         }
      }

      // Additional actors : using default notification parameters
      if (!is_null($useractors)) {
         // Observers : for mailcollector
         if (isset($input["_additional_observers"])
             && is_array($input["_additional_observers"])
             && count($input["_additional_observers"])) {

            $input2 = [$useractors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                 => CommonITILActor::OBSERVER,
                            '_from_object'                         => true];

            foreach ($input["_additional_observers"] as $tmp) {
               if (isset($tmp['users_id'])) {
                  foreach ($tmp as $key => $val) {
                     $input2[$key] = $val;
                  }
                  $useractors->add($input2);
               }
            }
         }

         if (isset($input["_additional_assigns"])
             && is_array($input["_additional_assigns"])
             && count($input["_additional_assigns"])) {

            $input2 = [$useractors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                 => CommonITILActor::ASSIGN,
                            '_from_object'                         => true];

            foreach ($input["_additional_assigns"] as $tmp) {
               if (isset($tmp['users_id'])) {
                  foreach ($tmp as $key => $val) {
                     $input2[$key] = $val;
                  }
                  $useractors->add($input2);
               }
            }
         }
         if (isset($input["_additional_requesters"])
             && is_array($input["_additional_requesters"])
             && count($input["_additional_requesters"])) {
            $input2 = [$useractors->getItilObjectForeignKey() => $this->fields['id'],
                            'type'                                 => CommonITILActor::REQUESTER,
                            '_from_object'                         => true];

            foreach ($input["_additional_requesters"] as $tmp) {
               if (isset($tmp['users_id'])) {
                  foreach ($tmp as $key => $val) {
                     $input2[$key] = $val;
                  }
                  $useractors->add($input2);
               }
            }
         }
      }
   }


   /**
    * Compute Priority
    *
    * @since 0.84
    *
    * @param $urgency   integer from 1 to 5
    * @param $impact    integer from 1 to 5
    *
    * @return integer from 1 to 5 (priority)
   **/
   static function computePriority($urgency, $impact) {
      global $CFG_GLPI;

      if (isset($CFG_GLPI[static::MATRIX_FIELD][$urgency][$impact])) {
         return $CFG_GLPI[static::MATRIX_FIELD][$urgency][$impact];
      }
      // Failback to trivial
      return round(($urgency+$impact)/2);
   }


   /**
    * Dropdown of ITIL object priority
    *
    * @since  version 0.84 new proto
    *
    * @param $options array of options
    *       - name     : select name (default is urgency)
    *       - value    : default value (default 0)
    *       - showtype : list proposed : normal, search (default normal)
    *       - wthmajor : boolean with major priority ?
    *       - display  : boolean if false get string
    *
    * @return string id of the select
   **/
   static function dropdownPriority(array $options = []) {

      $p['name']      = 'priority';
      $p['value']     = 0;
      $p['showtype']  = 'normal';
      $p['display']   = true;
      $p['withmajor'] = false;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $values = [];

      if ($p['showtype'] == 'search') {
         $values[0]  = static::getPriorityName(0);
         $values[-5] = static::getPriorityName(-5);
         $values[-4] = static::getPriorityName(-4);
         $values[-3] = static::getPriorityName(-3);
         $values[-2] = static::getPriorityName(-2);
         $values[-1] = static::getPriorityName(-1);
      }

      if (($p['showtype'] == 'search')
          || $p['withmajor']) {
         $values[6] = static::getPriorityName(6);
      }
      $values[5] = static::getPriorityName(5);
      $values[4] = static::getPriorityName(4);
      $values[3] = static::getPriorityName(3);
      $values[2] = static::getPriorityName(2);
      $values[1] = static::getPriorityName(1);

      return Dropdown::showFromArray($p['name'], $values, $p);
   }


   /**
    * Get ITIL object priority Name
    *
    * @param $value priority ID
   **/
   static function getPriorityName($value) {

      switch ($value) {
         case 6 :
            return _x('priority', 'Major');

         case 5 :
            return _x('priority', 'Very high');

         case 4 :
            return _x('priority', 'High');

         case 3 :
            return _x('priority', 'Medium');

         case 2 :
            return _x('priority', 'Low');

         case 1 :
            return _x('priority', 'Very low');

         // No standard one :
         case 0 :
            return _x('priority', 'All');
         case -1 :
            return _x('priority', 'At least very low');
         case -2 :
            return _x('priority', 'At least low');
         case -3 :
            return _x('priority', 'At least medium');
         case -4 :
            return _x('priority', 'At least high');
         case -5 :
            return _x('priority', 'At least very high');

         default :
            // Return $value if not define
            return $value;

      }
   }


   /**
    * Dropdown of ITIL object Urgency
    *
    * @since 0.84 new proto
    *
    * @param $options array of options
    *       - name     : select name (default is urgency)
    *       - value    : default value (default 0)
    *       - showtype : list proposed : normal, search (default normal)
    *       - display  : boolean if false get string
    *
    * @return string id of the select
   **/
   static function dropdownUrgency(array $options = []) {
      global $CFG_GLPI;

      $p['name']     = 'urgency';
      $p['value']    = 0;
      $p['showtype'] = 'normal';
      $p['display']  = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $values = [];

      if ($p['showtype'] == 'search') {
         $values[0]  = static::getUrgencyName(0);
         $values[-5] = static::getUrgencyName(-5);
         $values[-4] = static::getUrgencyName(-4);
         $values[-3] = static::getUrgencyName(-3);
         $values[-2] = static::getUrgencyName(-2);
         $values[-1] = static::getUrgencyName(-1);
      }

      if (isset($CFG_GLPI[static::URGENCY_MASK_FIELD])) {
         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::URGENCY_MASK_FIELD] & (1<<5))) {
            $values[5]  = static::getUrgencyName(5);
         }

         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::URGENCY_MASK_FIELD] & (1<<4))) {
            $values[4]  = static::getUrgencyName(4);
         }

         $values[3]  = static::getUrgencyName(3);

         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::URGENCY_MASK_FIELD] & (1<<2))) {
            $values[2]  = static::getUrgencyName(2);
         }

         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::URGENCY_MASK_FIELD] & (1<<1))) {
            $values[1]  = static::getUrgencyName(1);
         }
      }

      return Dropdown::showFromArray($p['name'], $values, $p);
   }


   /**
    * Get ITIL object Urgency Name
    *
    * @param $value urgency ID
   **/
   static function getUrgencyName($value) {

      switch ($value) {
         case 5 :
            return _x('urgency', 'Very high');

         case 4 :
            return _x('urgency', 'High');

         case 3 :
            return _x('urgency', 'Medium');

         case 2 :
            return _x('urgency', 'Low');

         case 1 :
            return _x('urgency', 'Very low');

         // No standard one :
         case 0 :
            return _x('urgency', 'All');
         case -1 :
            return _x('urgency', 'At least very low');
         case -2 :
            return _x('urgency', 'At least low');
         case -3 :
            return _x('urgency', 'At least medium');
         case -4 :
            return _x('urgency', 'At least high');
         case -5 :
            return _x('urgency', 'At least very high');

         default :
            // Return $value if not define
            return $value;

      }
   }


   /**
    * Dropdown of ITIL object Impact
    *
    * @since 0.84 new proto
    *
    * @param $options   array of options
    *  - name     : select name (default is impact)
    *  - value    : default value (default 0)
    *  - showtype : list proposed : normal, search (default normal)
    *  - display  : boolean if false get string
    *
    * \
    * @return string id of the select
   **/
   static function dropdownImpact(array $options = []) {
      global $CFG_GLPI;

      $p['name']      = 'impact';
      $p['value']     = 0;
      $p['showtype']  = 'normal';
      $p['display']   = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      $values = [];

      if ($p['showtype'] == 'search') {
         $values[0]  = static::getImpactName(0);
         $values[-5] = static::getImpactName(-5);
         $values[-4] = static::getImpactName(-4);
         $values[-3] = static::getImpactName(-3);
         $values[-2] = static::getImpactName(-2);
         $values[-1] = static::getImpactName(-1);
      }

      if (isset($CFG_GLPI[static::IMPACT_MASK_FIELD])) {
         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::IMPACT_MASK_FIELD] & (1<<5))) {
            $values[5]  = static::getImpactName(5);
         }

         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::IMPACT_MASK_FIELD] & (1<<4))) {
            $values[4]  = static::getImpactName(4);
         }

         $values[3]  = static::getImpactName(3);

         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::IMPACT_MASK_FIELD] & (1<<2))) {
            $values[2]  = static::getImpactName(2);
         }

         if (($p['showtype'] == 'search')
             || ($CFG_GLPI[static::IMPACT_MASK_FIELD] & (1<<1))) {
            $values[1]  = static::getImpactName(1);
         }
      }

      return Dropdown::showFromArray($p['name'], $values, $p);
   }


   /**
    * Get ITIL object Impact Name
    *
    * @param $value impact ID
   **/
   static function getImpactName($value) {

      switch ($value) {
         case 5 :
            return _x('impact', 'Very high');

         case 4 :
            return _x('impact', 'High');

         case 3 :
            return _x('impact', 'Medium');

         case 2 :
            return _x('impact', 'Low');

         case 1 :
            return _x('impact', 'Very low');

         // No standard one :
         case 0 :
            return _x('impact', 'All');
         case -1 :
            return _x('impact', 'At least very low');
         case -2 :
            return _x('impact', 'At least low');
         case -3 :
            return _x('impact', 'At least medium');
         case -4 :
            return _x('impact', 'At least high');
         case -5 :
            return _x('impact', 'At least very high');

         default :
            // Return $value if not define
            return $value;
      }
   }


   /**
    * Get the ITIL object status list
    *
    * @param $withmetaforsearch boolean (false by default)
    *
    * @return an array
   **/
   static function getAllStatusArray($withmetaforsearch = false) {

      // To be overridden by class
      $tab = [];

      return $tab;
   }


   /**
    * Get the ITIL object closed status list
    *
    * @since 0.83
    *
    * @return an array
   **/
   static function getClosedStatusArray() {

      // To be overridden by class
      $tab = [];
      return $tab;
   }


   /**
    * Get the ITIL object solved status list
    *
    * @since 0.83
    *
    * @return an array
   **/
   static function getSolvedStatusArray() {

      // To be overridden by class
      $tab = [];
      return $tab;
   }

   /**
    * Get the ITIL object all status list without solved and closed status
    *
    * @since 9.2.1
    *
    * @return array
   **/
   static function getNotSolvedStatusArray() {
      $all = static::getAllStatusArray();
      foreach (static::getSolvedStatusArray() as $status) {
         if (isset($all[$status])) {
            unset($all[$status]);
         }
      }
      foreach (static::getClosedStatusArray() as $status) {
         if (isset($all[$status])) {
            unset($all[$status]);
         }
      }
      $nosolved = array_keys($all);

      return $nosolved;
   }


   /**
    * Get the ITIL object new status list
    *
    * @since 0.83.8
    *
    * @return an array
   **/
   static function getNewStatusArray() {

      // To be overriden by class
      $tab = [];
      return $tab;
   }


   /**
    * Get the ITIL object process status list
    *
    * @since 0.83
    *
    * @return an array
   **/
   static function getProcessStatus() {

      // To be overridden by class
      $tab = [];
      return $tab;
   }


   /**
    * check is the user can change from / to a status
    *
    * @since 0.84
    *
    * @param $old       string value of old/current status
    * @param $new       string value of target status
    *
    * @return boolean
   **/
   static function isAllowedStatus($old, $new) {

      if (isset($_SESSION['glpiactiveprofile'][static::STATUS_MATRIX_FIELD][$old][$new])
          && !$_SESSION['glpiactiveprofile'][static::STATUS_MATRIX_FIELD][$old][$new]) {
         return false;
      }

      if (array_key_exists(static::STATUS_MATRIX_FIELD,
                           $_SESSION['glpiactiveprofile'])) { // maybe not set for post-only
         return true;
      }

      return false;
   }


   /**
    * Get the ITIL object status allowed for a current status
    *
    * @since 0.84 new proto
    *
    * @param $current   status
    *
    * @return an array
   **/
   static function getAllowedStatusArray($current) {

      $tab = static::getAllStatusArray();
      if (!isset($current)) {
         $current = self::INCOMING;
      }

      foreach ($tab as $status => $label) {
         if (($status != $current)
             && !self::isAllowedStatus($current, $status)) {
            unset($tab[$status]);
         }
      }
      return $tab;
   }

   /**
    * Is the ITIL object status exists for the object
    *
    * @since 0.85
    *
    * @param $status   status
    *
    * @return boolean
   **/
   static function isStatusExists($status) {

      $tab = static::getAllStatusArray();

      return isset($tab[$status]);
   }

   /**
    * Dropdown of object status
    *
    * @since 0.84 new proto
    *
    * @param $options   array of options
    *  - name     : select name (default is status)
    *  - value    : default value (default self::INCOMING)
    *  - showtype : list proposed : normal, search or allowed (default normal)
    *  - display  : boolean if false get string
    *
    * @return nothing (display)
   **/
   static function dropdownStatus(array $options = []) {

      $p['name']      = 'status';
      $p['showtype']  = 'normal';
      $p['display']   = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      if (!isset($p['value']) || empty($p['value'])) {
         $p['value']     = self::INCOMING;
      }

      switch ($p['showtype']) {
         case 'allowed' :
            $tab = static::getAllowedStatusArray($p['value']);
            break;

         case 'search' :
            $tab = static::getAllStatusArray(true);
            break;

         default :
            $tab = static::getAllStatusArray(false);
            break;
      }

      return Dropdown::showFromArray($p['name'], $tab, $p);
   }


   /**
    * Get ITIL object status Name
    *
    * @since 0.84
    *
    * @param $value     status ID
   **/
   static function getStatus($value) {

      $tab  = static::getAllStatusArray(true);
      // Return $value if not defined
      return (isset($tab[$value]) ? $tab[$value] : $value);
   }


   /**
    * get field part name corresponding to actor type
    *
    * @param $type      integer : user type
    *
    * @since 0.84.6
    *
    * @return get typename
   **/
   static function getActorFieldNameType($type) {

      switch ($type) {
         case CommonITILActor::REQUESTER :
            return 'requester';

         case CommonITILActor::OBSERVER :
            return 'observer';

         case CommonITILActor::ASSIGN :
            return 'assign';

         default :
            return false;
      }
   }


   /**
    * show groups asociated
    *
    * @param $type      integer : user type
    * @param $canedit   boolean : can edit ?
    * @param $options   array    options for default values ($options of showForm)
    *
    * @return nothing display
   **/
   function showGroupsAssociated($type, $canedit, array $options = []) {
      global $CFG_GLPI;

      $groupicon = self::getActorIcon('group', $type);
      $group     = new Group();
      $linkclass = new $this->grouplinkclass();

      $itemtype  = $this->getType();
      $typename  = self::getActorFieldNameType($type);

      $candelete = true;
      $mandatory = '';
      // For ticket templates : mandatories
      if (($itemtype == 'Ticket')
          && isset($options['_tickettemplate'])) {
         $mandatory = $options['_tickettemplate']->getMandatoryMark("_groups_id_".$typename);
         if ($options['_tickettemplate']->isMandatoryField("_groups_id_".$typename)
             && isset($this->groups[$type]) && (count($this->groups[$type])==1)) {
            $candelete = false;
         }
      }

      if (isset($this->groups[$type]) && count($this->groups[$type])) {
         foreach ($this->groups[$type] as $d) {
            echo "<div class='actor_row'>";
            $k = $d['groups_id'];
            echo "$mandatory$groupicon&nbsp;";
            if ($group->getFromDB($k)) {
               echo $group->getLink(['comments' => true]);
            }
            if ($canedit && $candelete) {
               Html::showSimpleForm($linkclass->getFormURL(), 'delete',
                                    _x('button', 'Delete permanently'),
                                    ['id' => $d['id']],
                                    'fa-times-circle');
            }
            echo "</div>";
         }
      }
   }

   /**
    * show suppliers associated
    *
    * @since 0.84
    *
    * @param $type      integer : user type
    * @param $canedit   boolean : can edit ?
    * @param $options   array    options for default values ($options of showForm)
    *
    * @return nothing display
   **/
   function showSuppliersAssociated($type, $canedit, array $options = []) {
      global $CFG_GLPI;

      $showsupplierlink = 0;
      if (Session::haveRight('contact_enterprise', READ)) {
         $showsupplierlink = 2;
      }

      $suppliericon = self::getActorIcon('supplier', $type);
      $supplier     = new Supplier();
      $linksupplier = new $this->supplierlinkclass();

      $itemtype     = $this->getType();
      $typename     = self::getActorFieldNameType($type);

      $candelete    = true;
      $mandatory    = '';
      // For ticket templates : mandatories
      if (($itemtype == 'Ticket')
          && isset($options['_tickettemplate'])) {
         $mandatory = $options['_tickettemplate']->getMandatoryMark("_suppliers_id_".$typename);
         if ($options['_tickettemplate']->isMandatoryField("_suppliers_id_".$typename)
             && isset($this->suppliers[$type]) && (count($this->suppliers[$type])==1)) {
            $candelete = false;
         }
      }

      if (isset($this->suppliers[$type]) && count($this->suppliers[$type])) {
         foreach ($this->suppliers[$type] as $d) {
            echo "<div class='actor_row'>";
            $k = $d['suppliers_id'];
            echo "$mandatory$suppliericon&nbsp;";
            if ($supplier->getFromDB($k)) {
               echo $supplier->getLink(['comments' => $showsupplierlink]);
               echo "&nbsp;";
               $tmpname = Dropdown::getDropdownName($supplier->getTable(), $k, 1);
               Html::showToolTip($tmpname['comment']);

               if ($CFG_GLPI['notifications_mailing']) {
                  $text = __('Email followup')."&nbsp;".Dropdown::getYesNo($d['use_notification']).
                  '<br>';

                  if ($d['use_notification']) {
                     $supemail = $d['alternative_email'];
                     if (empty($supemail)) {
                        $supemail = $supplier->fields['email'];
                     }
                     $text .= sprintf(__('%1$s: %2$s'), __('Email'), $supemail);
                  }
                  if ($canedit) {
                     $opt = ['awesome-class' => 'fa-envelope',
                                  'popup' => $linksupplier->getFormURLWithID($d['id'])];
                     Html::showToolTip($text, $opt);
                  }

               }

            }
            if ($canedit && $candelete) {
               Html::showSimpleForm($linksupplier->getFormURL(), 'delete',
                                    _x('button', 'Delete permanently'),
                                    ['id' => $d['id']],
                                    'fa-times-circle');
            }
            echo '</div>';
         }
      }
   }

   /**
    * display a value according to a field
    *
    * @since 0.83
    *
    * @param $field     String         name of the field
    * @param $values    String / Array with the value to display
    * @param $options   Array          of option
    *
    * @return a string
   **/
   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'status':
            return self::getStatus($values[$field]);

         case 'urgency':
            return self::getUrgencyName($values[$field]);

         case 'impact':
            return self::getImpactName($values[$field]);

         case 'priority':
            return self::getPriorityName($values[$field]);

         case 'global_validation' :
            return CommonITILValidation::getStatus($values[$field]);

      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   /**
    * @since 0.84
    *
    * @param $field
    * @param $name            (default '')
    * @param $values          (default '')
    * @param $options   array
   **/
   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'status' :
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownStatus($options);

         case 'impact' :
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownImpact($options);

         case 'urgency' :
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownUrgency($options);

         case 'priority' :
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownPriority($options);

         case 'global_validation' :
            $options['global'] = true;
            $options['value']  = $values[$field];
            return CommonITILValidation::dropdownStatus($name, $options);
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }


   /**
    * @since 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
   **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case 'add_task' :
            $itemtype = $ma->getItemtype(true);
            $tasktype = $itemtype.'Task';
            if ($ttype = getItemForItemtype($tasktype)) {
               $ttype->showFormMassiveAction();
               return true;
            }
            return false;

         case 'add_actor' :
            $types            = [0                          => Dropdown::EMPTY_VALUE,
                                      CommonITILActor::REQUESTER => __('Requester'),
                                      CommonITILActor::OBSERVER  => __('Watcher'),
                                      CommonITILActor::ASSIGN    => __('Assigned to')];
            $rand             = Dropdown::showFromArray('actortype', $types);

            $paramsmassaction = ['actortype' => '__VALUE__'];

            Ajax::updateItemOnSelectEvent("dropdown_actortype$rand", "show_massiveaction_field",
                                          $CFG_GLPI["root_doc"].
                                             "/ajax/dropdownMassiveActionAddActor.php",
                                          $paramsmassaction);
            echo "<span id='show_massiveaction_field'>&nbsp;</span>\n";
            return true;
         case 'update_notif' :

            Dropdown::showYesNo('use_notification');
            echo "<br><br>";
            echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
            return true;
            return true;
      }
      return parent::showMassiveActionsSubForm($ma);
   }


   /**
    * @since 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case 'add_actor' :
            $input = $ma->getInput();
            foreach ($ids as $id) {
               $input2 = ['id' => $id];
               if (isset($input['_itil_requester'])) {
                  $input2['_itil_requester'] = $input['_itil_requester'];
               }
               if (isset($input['_itil_observer'])) {
                  $input2['_itil_observer'] = $input['_itil_observer'];
               }
               if (isset($input['_itil_assign'])) {
                  $input2['_itil_assign'] = $input['_itil_assign'];
               }
               if ($item->can($id, UPDATE)) {
                  if ($item->update($input2)) {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                  }
               } else {
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            return;

         case 'update_notif' :
            $input = $ma->getInput();
            foreach ($ids as $id) {
               if ($item->can($id, UPDATE)) {
                  $linkclass = new $item->userlinkclass();
                  foreach ($linkclass->getActors($id) as $type => $users) {
                     foreach ($users as $data) {
                        $data['use_notification'] = $input['use_notification'];
                        $linkclass->update($data);
                     }
                  }
                  $linkclass = new $item->supplierlinkclass();
                  foreach ($linkclass->getActors($id) as $type => $users) {
                     foreach ($users as $data) {
                        $data['use_notification'] = $input['use_notification'];
                        $linkclass->update($data);
                     }
                  }

                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
               } else {
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                  $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
               }
            }
            return;

         case 'add_task' :
            if (!($task = getItemForItemtype($item->getType().'Task'))) {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
               break;
            }
            $field = $item->getForeignKeyField();

            $input = $ma->getInput();

            foreach ($ids as $id) {
               if ($item->getFromDB($id)) {
                  $input2 = [$field              => $id,
                                  'taskcategories_id' => $input['taskcategories_id'],
                                  'actiontime'        => $input['actiontime'],
                                  'content'           => $input['content']];
                  if ($task->can(-1, CREATE, $input2)) {
                     if ($task->add($input2)) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                     } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                     }
                  } else {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                     $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                  }
               } else {
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                  $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
               }
            }
            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }


   /**
    * @since 0.85
   **/
   function getSearchOptionsMain() {
      global $CFG_GLPI;

      $tab = [];

      $tab[] = [
         'id'                 => 'common',
         'name'               => __('Characteristics')
      ];

      $tab[] = [
         'id'                 => '1',
         'table'              => $this->getTable(),
         'field'              => 'name',
         'name'               => __('Title'),
         'datatype'           => 'itemlink',
         'searchtype'         => 'contains',
         'massiveaction'      => false,
         'additionalfields'   => ['id', 'content', 'status']
      ];

      $newtab = [
         'id'                 => '21',
         'table'              => $this->getTable(),
         'field'              => 'content',
         'name'               => __('Description'),
         'massiveaction'      => false,
         'datatype'           => 'text'
      ];
      if ($this->getType() == 'Ticket'
          && $CFG_GLPI["use_rich_text"]) {
         $newtab['htmltext'] = true;
      }
      $tab[] = $newtab;

      $tab[] = [
         'id'                 => '2',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('ID'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => '12',
         'table'              => $this->getTable(),
         'field'              => 'status',
         'name'               => __('Status'),
         'searchtype'         => 'equals',
         'datatype'           => 'specific'
      ];

      $tab[] = [
         'id'                 => '10',
         'table'              => $this->getTable(),
         'field'              => 'urgency',
         'name'               => __('Urgency'),
         'searchtype'         => 'equals',
         'datatype'           => 'specific'
      ];

      $tab[] = [
         'id'                 => '11',
         'table'              => $this->getTable(),
         'field'              => 'impact',
         'name'               => __('Impact'),
         'searchtype'         => 'equals',
         'datatype'           => 'specific'
      ];

      $tab[] = [
         'id'                 => '3',
         'table'              => $this->getTable(),
         'field'              => 'priority',
         'name'               => __('Priority'),
         'searchtype'         => 'equals',
         'datatype'           => 'specific'
      ];

      $tab[] = [
         'id'                 => '15',
         'table'              => $this->getTable(),
         'field'              => 'date',
         'name'               => __('Opening date'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '16',
         'table'              => $this->getTable(),
         'field'              => 'closedate',
         'name'               => __('Closing date'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '18',
         'table'              => $this->getTable(),
         'field'              => 'time_to_resolve',
         'name'               => __('Time to resolve'),
         'datatype'           => 'datetime',
         'maybefuture'        => true,
         'massiveaction'      => false,
         'additionalfields'   => ['status']
      ];

      $tab[] = [
         'id'                 => '151',
         'table'              => $this->getTable(),
         'field'              => 'time_to_resolve',
         'name'               => __('Time to resolve + Progress'),
         'massiveaction'      => false,
         'nosearch'           => true,
         'additionalfields'   => ['status']
      ];

      $tab[] = [
         'id'                 => '82',
         'table'              => $this->getTable(),
         'field'              => 'is_late',
         'name'               => __('Time to resolve exceedeed'),
         'datatype'           => 'bool',
         'massiveaction'      => false,
         'computation'        => 'IF(TABLE.`time_to_resolve` IS NOT NULL
                                            AND TABLE.`status` <> 4
                                            AND (TABLE.`solvedate` > TABLE.`time_to_resolve`
                                                 OR (TABLE.`solvedate` IS NULL
                                                      AND TABLE.`time_to_resolve` < NOW())),
                                            1, 0)'
      ];

      $tab[] = [
         'id'                 => '17',
         'table'              => $this->getTable(),
         'field'              => 'solvedate',
         'name'               => __('Resolution date'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '19',
         'table'              => $this->getTable(),
         'field'              => 'date_mod',
         'name'               => __('Last update'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $newtab = [
         'id'                 => '7',
         'table'              => 'glpi_itilcategories',
         'field'              => 'completename',
         'name'               => __('Category'),
         'datatype'           => 'dropdown'
      ];

      if (!Session::isCron() // no filter for cron
          && Session::getCurrentInterface() == 'helpdesk') {
         $newtab['condition']         = "`is_helpdeskvisible`";
      }
      $tab[] = $newtab;

      $tab[] = [
         'id'                 => '80',
         'table'              => 'glpi_entities',
         'field'              => 'completename',
         'name'               => __('Entity'),
         'massiveaction'      => false,
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '45',
         'table'              => $this->getTable(),
         'field'              => 'actiontime',
         'name'               => __('Total duration'),
         'datatype'           => 'timestamp',
         'massiveaction'      => false,
         'nosearch'           => true
      ];

      $newtab = [
         'id'                 => '64',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'linkfield'          => 'users_id_lastupdater',
         'name'               => __('Last edit by'),
         'massiveaction'      => false,
         'datatype'           => 'dropdown',
         'right'              => 'all'
      ];

      // Filter search fields for helpdesk
      if (!Session::isCron() // no filter for cron
          && Session::getCurrentInterface() != 'central') {
         // last updater no search
         $newtab['nosearch'] = true;
      }
      $tab[] = $newtab;

      // add objectlock search options
      $tab = array_merge($tab, ObjectLock::rawSearchOptionsToAdd(get_class($this)));

      return $tab;
   }


   /**
    * @since 0.85
   **/
   function getSearchOptionsSolution() {
      $tab[] = [
         'id'                 => 'solution',
         'name'               => _n('Solution', 'Solutions', 1)
      ];

      $tab[] = [
         'id'                 => '23',
         'table'              => 'glpi_solutiontypes',
         'field'              => 'name',
         'name'               => __('Solution type'),
         'datatype'           => 'dropdown',
         'forcegroupby'       => true,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => ITILSolution::getTable(),
               'joinparams'         => [
                  'jointype'           => 'itemtype_item',
               ]
            ]
         ]
      ];

      $tab[] = [
         'id'                 => '24',
         'table'              => ITILSolution::getTable(),
         'field'              => 'content',
         'name'               => _n('Solution', 'Solutions', 1),
         'datatype'           => 'text',
         'htmltext'           => true,
         'massiveaction'      => false,
         'forcegroupby'       => true,
         'joinparams'         => [
            'jointype'           => 'itemtype_item'
         ]
      ];

      return $tab;
   }


   function getSearchOptionsStats() {
      $tab = [];

      $tab[] = [
         'id'                 => 'stats',
         'name'               => __('Statistics')
      ];

      $tab[] = [
         'id'                 => '154',
         'table'              => $this->getTable(),
         'field'              => 'solve_delay_stat',
         'name'               => __('Resolution time'),
         'datatype'           => 'timestamp',
         'forcegroupby'       => true,
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '152',
         'table'              => $this->getTable(),
         'field'              => 'close_delay_stat',
         'name'               => __('Closing time'),
         'datatype'           => 'timestamp',
         'forcegroupby'       => true,
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '153',
         'table'              => $this->getTable(),
         'field'              => 'waiting_duration',
         'name'               => __('Waiting time'),
         'datatype'           => 'timestamp',
         'forcegroupby'       => true,
         'massiveaction'      => false
      ];

      return $tab;
   }


   function getSearchOptionsActors() {
      $tab = [];

      $tab[] = [
         'id'                 => 'requester',
         'name'               => __('Requester')
      ];

      $newtab = [
         'id'                 => '4', // Also in Ticket_User::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_users',
         'field'              => 'name',
         'datatype'           => 'dropdown',
         'right'              => 'all',
         'name'               => __('Requester'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->userlinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::REQUESTER
               ]
            ]
         ]
      ];

      if (!Session::isCron() // no filter for cron
          && Session::getCurrentInterface() == 'helpdesk') {
         $newtab['right']       = 'id';
      }
      $tab[] = $newtab;

      $newtab = [
         'id'                 => '71',  // Also in Group_Ticket::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_groups',
         'field'              => 'completename',
         'datatype'           => 'dropdown',
         'name'               => __('Requester group'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'condition'          => 'is_requester',
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->grouplinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::REQUESTER
               ]
            ]
         ]
      ];

      if (!Session::isCron() // no filter for cron
          && Session::getCurrentInterface() == 'helpdesk') {
         $newtab['condition']       .= " AND `id` IN (".implode(",", $_SESSION['glpigroups']).")";
      }
      $tab[] = $newtab;

      $newtab = [
         'id'                 => '22',
         'table'              => 'glpi_users',
         'field'              => 'name',
         'datatype'           => 'dropdown',
         'right'              => 'all',
         'linkfield'          => 'users_id_recipient',
         'name'               => __('Writer')
      ];

      if (!Session::isCron() // no filter for cron
          && Session::getCurrentInterface() == 'helpdesk') {
         $newtab['right']       = 'id';
      }
      $tab[] = $newtab;

      $tab[] = [
         'id'                 => 'observer',
         'name'               => __('Watcher')
      ];

      $tab[] = [
         'id'                 => '66', // Also in Ticket_User::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_users',
         'field'              => 'name',
         'datatype'           => 'dropdown',
         'right'              => 'all',
         'name'               => __('Watcher'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->userlinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::OBSERVER
               ]
            ]
         ]
      ];

      $tab[] = [
         'id'                 => '65', // Also in Group_Ticket::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_groups',
         'field'              => 'completename',
         'datatype'           => 'dropdown',
         'name'               => __('Watcher group'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'condition'          => 'is_requester',
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->grouplinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::OBSERVER
               ]
            ]
         ]
      ];

      $tab[] = [
         'id'                 => 'assign',
         'name'               => __('Assigned to')
      ];

      $tab[] = [
         'id'                 => '5', // Also in Ticket_User::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_users',
         'field'              => 'name',
         'datatype'           => 'dropdown',
         'right'              => 'own_ticket',
         'name'               => __('Technician'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->userlinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::ASSIGN
               ]
            ]
         ]
      ];

      $tab[] = [
         'id'                 => '6', // Also in Supplier_Ticket::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_suppliers',
         'field'              => 'name',
         'datatype'           => 'dropdown',
         'name'               => __('Assigned to a supplier'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->supplierlinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::ASSIGN
               ]
            ]
         ]
      ];

      $tab[] = [
         'id'                 => '8', // Also in Group_Ticket::post_addItem() and Log::getHistoryData()
         'table'              => 'glpi_groups',
         'field'              => 'completename',
         'datatype'           => 'dropdown',
         'name'               => __('Technician group'),
         'forcegroupby'       => true,
         'massiveaction'      => false,
         'condition'          => 'is_assign',
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => getTableForItemType($this->grouplinkclass),
               'joinparams'         => [
                  'jointype'           => 'child',
                  'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::ASSIGN
               ]
            ]
         ]
      ];

      $tab[] = [
         'id'                 => 'notification',
         'name'               => _n('Notification', 'Notifications', Session::getPluralNumber())
      ];

      $tab[] = [
         'id'                 => '35',
         'table'              => getTableForItemType($this->userlinkclass),
         'field'              => 'use_notification',
         'name'               => __('Email followup'),
         'datatype'           => 'bool',
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'child',
            'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::REQUESTER
         ]
      ];

      $tab[] = [
         'id'                 => '34',
         'table'              => getTableForItemType($this->userlinkclass),
         'field'              => 'alternative_email',
         'name'               => __('Email for followup'),
         'datatype'           => 'email',
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'child',
            'condition'          => 'AND NEWTABLE.`type` = '.CommonITILActor::REQUESTER
         ]
      ];

      return $tab;
   }

   /**
    * Get status icon
    *
    * @since 9.3
    *
    * @return string
    */
   public static function getStatusIcon($status) {
      $class = static::getStatusClass($status);
      $label = static::getStatus($status);
      return "<i class='$class' title='$label'></i>";
   }

   /**
    * Get status class
    *
    * @since 9.3
    *
    * @return string
    */
   public static function getStatusClass($status) {
      $class = null;
      switch ($status) {
         case self::INCOMING :
            $class = 'circle';
            break;
         case self::ASSIGNED :
            $class = 'circle-o';
            break;
         case self::PLANNED :
            $class = 'calendar';
            break;
         case self::WAITING :
            $class = 'circle';
            break;
         case self::SOLVED :
            $class = 'circle-o';
            break;
         case self::CLOSED :
            $class = 'circle';
            break;
         case self::ACCEPTED :
            $class = 'check-circle';
            break;
         case self::OBSERVED :
            $class = 'eye';
            break;
         case self::EVALUATION :
            $class = 'circle-o';
            break;
         case self::APPROVAL :
            $class = 'question-circle';
            break;
         case self::TEST :
            $class = 'question-circle';
            break;
         case self::QUALIFICATION :
            $class = 'circle-o';
            break;
      }

      return $class == null
         ? ''
         : 'itilstatus fa fa-' . $class." ".self::getStatusKey($status);
   }

   /**
    * Get status key
    *
    * @since 9.3
    *
    * @return string
    */
   public static function getStatusKey($status) {
      $key = '';
      switch ($status) {
         case self::INCOMING :
            $key = 'new';
            break;
         case self::ASSIGNED :
            $key = 'assigned';
            break;
         case self::PLANNED :
            $key = 'planned';
            break;
         case self::WAITING :
            $key = 'waiting';
            break;
         case self::SOLVED :
            $key = 'solved';
            break;
         case self::CLOSED :
            $key = 'closed';
            break;
         case self::ACCEPTED :
            $key = 'accepted';
            break;
         case self::OBSERVED :
            $key = 'observe';
            break;
         case self::EVALUATION :
            $key = 'eval';
            break;
         case self::APPROVAL :
            $key = 'approval';
            break;
         case self::TEST :
            $key = 'test';
            break;
         case self::QUALIFICATION :
            $key = 'qualif';
            break;
      }
      return $key;
   }

   /**
    * Get status icon URL
    *
    * @since 0.84
    *
    * @deprecated 9.3
    *
    * @param $status status to get icon URL
    *
    * @return icon URL
   **/
   static function getStatusIconURL($status) {
      global $CFG_GLPI;

      Toolbox::deprecated("Use CommonITILObject::getStatusIcon()");

      switch ($status) {
         case self::INCOMING :
            return $CFG_GLPI["root_doc"]."/pics/new.png";

         case self::ASSIGNED :
            return $CFG_GLPI["root_doc"]."/pics/assign.png";

         case self::PLANNED :
            return $CFG_GLPI["root_doc"]."/pics/plan.png";

         case self::WAITING :
            return $CFG_GLPI["root_doc"]."/pics/waiting.png";

         case self::SOLVED :
            return $CFG_GLPI["root_doc"]."/pics/solved.png";

         case self::CLOSED :
            return $CFG_GLPI["root_doc"]."/pics/closed.png";

         case self::ACCEPTED :
            return $CFG_GLPI["root_doc"]."/pics/accepted.png";

         case self::OBSERVED :
            return $CFG_GLPI["root_doc"]."/pics/observe.png";

         case self::EVALUATION :
            return $CFG_GLPI["root_doc"]."/pics/evaluation.png";

         case self::APPROVAL :
            return $CFG_GLPI["root_doc"]."/pics/approbation.png";

         case self::TEST :
            return $CFG_GLPI["root_doc"]."/pics/test.png";

         case self::QUALIFICATION :
            return $CFG_GLPI["root_doc"]."/pics/qualification.png";
      }
      return '';
   }


   /**
    * show Icon for Actor
    *
    * @param $user_group   string   'user or 'group'
    * @param $type         integer  user/group type
    *
    * @return nothing display
   **/
   static function getActorIcon($user_group, $type) {
      global $CFG_GLPI;

      switch ($user_group) {
         case 'user' :
            $icontitle = __s('User').' - '.$type; // should never be used
            switch ($type) {
               case CommonITILActor::REQUESTER :
                  $icontitle = __s('Requester user');
                  break;

               case CommonITILActor::OBSERVER :
                  $icontitle = __s('Watcher user');
                  break;

               case CommonITILActor::ASSIGN :
                  $icontitle = __s('Technician');
                  break;
            }
            return "<img src='".$CFG_GLPI['root_doc']."/pics/user.png'
                     alt=\"$icontitle\" title=\"$icontitle\">";

         case 'group' :
            $icontitle = __('Group');
            switch ($type) {
               case CommonITILActor::REQUESTER :
                  $icontitle = __s('Requester group');
                  break;

               case CommonITILActor::OBSERVER :
                  $icontitle = __s('Watcher group');
                  break;

               case CommonITILActor::ASSIGN :
                  $icontitle = __s('Group in charge of the ticket');
                  break;
            }
            return  "<img src='".$CFG_GLPI['root_doc']."/pics/group.png'
                      alt=\"$icontitle\" title=\"$icontitle\">";

         case 'supplier' :
            $icontitle = __('Supplier');
            return  "<img src='".$CFG_GLPI['root_doc']."/pics/supplier.png'
                      alt=\"$icontitle\" title=\"$icontitle\">";

      }
      return '';

   }


   /**
    * show tooltip for user notification information
    *
    * @param $type      integer  user type
    * @param $canedit   boolean  can edit ?
    * @param $options   array    options for default values ($options of showForm)
    *
    * @return nothing display
   **/
   function showUsersAssociated($type, $canedit, array $options = []) {
      global $CFG_GLPI;

      $showuserlink = 0;
      if (User::canView()) {
         $showuserlink = 2;
      }
      $usericon  = self::getActorIcon('user', $type);
      $user      = new User();
      $linkuser  = new $this->userlinkclass();

      $itemtype  = $this->getType();
      $typename  = self::getActorFieldNameType($type);

      $candelete = true;
      $mandatory = '';
      // For ticket templates : mandatories
      if (($itemtype == 'Ticket')
          && isset($options['_tickettemplate'])) {
         $mandatory = $options['_tickettemplate']->getMandatoryMark("_users_id_".$typename);
         if ($options['_tickettemplate']->isMandatoryField("_users_id_".$typename)
             && isset($this->users[$type]) && (count($this->users[$type])==1)) {
            $candelete = false;
         }
      }

      if (isset($this->users[$type]) && count($this->users[$type])) {
         foreach ($this->users[$type] as $d) {
            echo "<div class='actor_row'>";
            $k = $d['users_id'];

            echo "$mandatory$usericon&nbsp;";

            if ($k) {
               $userdata = getUserName($k, 2);
            } else {
               $email         = $d['alternative_email'];
               $userdata      = "<a href='mailto:$email'>$email</a>";
            }

            if ($k) {
               $param = ['display' => false];
               if ($showuserlink) {
                  $param['link'] = $userdata["link"];
               }
               echo $userdata['name']."&nbsp;".Html::showToolTip($userdata["comment"], $param);
            } else {
               echo $userdata;
            }

            if ($CFG_GLPI['notifications_mailing']) {
               $text = __('Email followup')."&nbsp;".Dropdown::getYesNo($d['use_notification']).
                       '<br>';

               if ($d['use_notification']) {
                  $uemail = $d['alternative_email'];
                  if (empty($uemail) && $user->getFromDB($d['users_id'])) {
                     $uemail = $user->getDefaultEmail();
                  }
                  $text .= sprintf(__('%1$s: %2$s'), __('Email'), $uemail);
                  if (!NotificationMailing::isUserAddressValid($uemail)) {
                     $text .= "&nbsp;<span class='red'>".__('Invalid email address')."</span>";
                  }
               }

               if ($canedit
                   || ($d['users_id'] == Session::getLoginUserID())) {
                  $opt      = ['awesome-class' => 'fa-envelope',
                                    'popup' => $linkuser->getFormURLWithID($d['id'])];
                  Html::showToolTip($text, $opt);
               }
            }

            if ($canedit && $candelete) {
               Html::showSimpleForm($linkuser->getFormURL(), 'delete',
                                    _x('button', 'Delete permanently'),
                                    ['id' => $d['id']],
                                    'fa-times-circle');
            }
            echo "</div>";
         }
      }
   }


   /**
    * show actor add div
    *
    * @param $type         string   actor type
    * @param $rand_type    integer  rand value of div to use
    * @param $entities_id  integer  entity ID
    * @param $is_hidden    array    of hidden fields (if empty consider as not hidden)
    * @param $withgroup    boolean  allow adding a group (true by default)
    * @param $withsupplier boolean  allow adding a supplier (only one possible in ASSIGN case)
    *                               (false by default)
    * @param $inobject     boolean  display in ITIL object ? (true by default)
    *
    * @return nothing display
   **/
   function showActorAddForm($type, $rand_type, $entities_id, $is_hidden = [],
                             $withgroup = true, $withsupplier = false, $inobject = true) {
      global $CFG_GLPI;

      $types = ['user'  => __('User')];

      if ($withgroup) {
         $types['group'] = __('Group');
      }

      if ($withsupplier
          && ($type == CommonITILActor::ASSIGN)) {
         $types['supplier'] = __('Supplier');
      }

      $typename = self::getActorFieldNameType($type);
      switch ($type) {
         case CommonITILActor::REQUESTER :
            if (isset($is_hidden['_users_id_requester']) && $is_hidden['_users_id_requester']) {
               unset($types['user']);
            }
            if (isset($is_hidden['_groups_id_requester']) && $is_hidden['_groups_id_requester']) {
               unset($types['group']);
            }
            break;

         case CommonITILActor::OBSERVER :
            if (isset($is_hidden['_users_id_observer']) && $is_hidden['_users_id_observer']) {
               unset($types['user']);
            }
            if (isset($is_hidden['_groups_id_observer']) && $is_hidden['_groups_id_observer']) {
               unset($types['group']);
            }
            break;

         case CommonITILActor::ASSIGN :
            if (isset($is_hidden['_users_id_assign']) && $is_hidden['_users_id_assign']) {
               unset($types['user']);
            }
            if (isset($is_hidden['_groups_id_assign']) && $is_hidden['_groups_id_assign']) {
               unset($types['group']);
            }
            if (isset($types['supplier'])
               && isset($is_hidden['_suppliers_id_assign']) && $is_hidden['_suppliers_id_assign']) {
               unset($types['supplier']);
            }
            break;

         default :
            return false;
      }

      echo "<div ".($inobject?"style='display:none'":'')." id='itilactor$rand_type' class='actor-dropdown'>";
      $rand   = Dropdown::showFromArray("_itil_".$typename."[_type]", $types,
                                        ['display_emptychoice' => true]);
      $params = ['type'            => '__VALUE__',
                      'actortype'       => $typename,
                      'itemtype'        => $this->getType(),
                      'allow_email'     => (($type == CommonITILActor::OBSERVER)
                                            || $type == CommonITILActor::REQUESTER),
                      'entity_restrict' => $entities_id,
                      'use_notif'       => Entity::getUsedConfig('is_notif_enable_default', $entities_id, '', 1)];

      Ajax::updateItemOnSelectEvent("dropdown__itil_".$typename."[_type]$rand",
                                    "showitilactor".$typename."_$rand",
                                    $CFG_GLPI["root_doc"]."/ajax/dropdownItilActors.php",
                                    $params);
      echo "<span id='showitilactor".$typename."_$rand' class='actor-dropdown'>&nbsp;</span>";
      if ($inobject) {
         echo "<hr>";
      }
      echo "</div>";
   }


   /**
    * show user add div on creation
    *
    * @param $type      integer  actor type
    * @param $options   array    options for default values ($options of showForm)
    *
    * @return nothing display
   **/
   function showActorAddFormOnCreate($type, array $options) {
      global $CFG_GLPI;

      $typename = self::getActorFieldNameType($type);

      $itemtype = $this->getType();

      echo self::getActorIcon('user', $type);
      // For ticket templates : mandatories
      if (($itemtype == 'Ticket')
          && isset($options['_tickettemplate'])) {
         echo $options['_tickettemplate']->getMandatoryMark("_users_id_".$typename);
      }
      echo "&nbsp;";

      if (!isset($options["_right"])) {
         $right = $this->getDefaultActorRightSearch($type);
      } else {
         $right = $options["_right"];
      }

      if ($options["_users_id_".$typename] == 0 && !isset($_REQUEST["_users_id_$typename"]) && !isset($this->input["_users_id_$typename"])) {
         $options["_users_id_".$typename] = $this->getDefaultActor($type);
      }
      $rand   = mt_rand();
      $actor_name = '_users_id_'.$typename;
      if ($type == CommonITILActor::OBSERVER) {
         $actor_name = '_users_id_'.$typename.'[]';
      }
      $params = ['name'        => $actor_name,
                      'value'       => $options["_users_id_".$typename],
                      'right'       => $right,
                      'rand'        => $rand,
                      'entity'      => (isset($options['entities_id'])
                                        ? $options['entities_id']: $options['entity_restrict'])];

      //only for active ldap and corresponding right
      $ldap_methods = getAllDatasFromTable('glpi_authldaps', '`is_active`=1');
      if (count($ldap_methods)
            && Session::haveRight('user', User::IMPORTEXTAUTHUSERS)) {
         $params['ldap_import'] = true;
      }

      if ($this->userentity_oncreate
          && ($type == CommonITILActor::REQUESTER)) {
         $params['on_change'] = 'this.form.submit()';
         unset($params['entity']);
      }

      $params['_user_index'] = 0;
      if (isset($options['_user_index'])) {
         $params['_user_index'] = $options['_user_index'];
      }

      if ($CFG_GLPI['notifications_mailing']) {
         $paramscomment
            = ['value' => '__VALUE__',
                    'field' => "_users_id_".$typename."_notif",
                    '_user_index'
                            => $params['_user_index'],
                    'allow_email'
                            => (($type == CommonITILActor::REQUESTER)
                                || ($type == CommonITILActor::OBSERVER)),
                    'use_notification'
                            => $options["_users_id_".$typename."_notif"]['use_notification']];
         if (isset($options["_users_id_".$typename."_notif"]['alternative_email'])) {
            $paramscomment['alternative_email']
               = $options["_users_id_".$typename."_notif"]['alternative_email'];
         }
         $params['toupdate'] = ['value_fieldname'
                                                  => 'value',
                                     'to_update'  => "notif_".$typename."_$rand",
                                     'url'        => $CFG_GLPI["root_doc"]."/ajax/uemailUpdate.php",
                                     'moreparams' => $paramscomment];

      }

      if (($itemtype == 'Ticket')
          && ($type == CommonITILActor::ASSIGN)) {
         $toupdate = [];
         if (isset($params['toupdate']) && is_array($params['toupdate'])) {
            $toupdate[] = $params['toupdate'];
         }
         $toupdate[] = ['value_fieldname' => 'value',
                             'to_update'       => "countassign_$rand",
                             'url'             => $CFG_GLPI["root_doc"].
                                                      "/ajax/ticketassigninformation.php",
                             'moreparams'      => ['users_id_assign' => '__VALUE__']];
         $params['toupdate'] = $toupdate;
      }

      // List all users in the active entities
      User::dropdown($params);

      if ($itemtype == 'Ticket') {

         // display opened tickets for user
         if (($type == CommonITILActor::REQUESTER)
             && ($options["_users_id_".$typename] > 0)
             && (Session::getCurrentInterface() != "helpdesk")) {

            $options2['criteria'][0]['field']      = 4; // users_id
            $options2['criteria'][0]['searchtype'] = 'equals';
            $options2['criteria'][0]['value']      = $options["_users_id_".$typename];
            $options2['criteria'][0]['link']       = 'AND';

            $options2['criteria'][1]['field']      = 12; // status
            $options2['criteria'][1]['searchtype'] = 'equals';
            $options2['criteria'][1]['value']      = 'notold';
            $options2['criteria'][1]['link']       = 'AND';

            $options2['reset'] = 'reset';

            $url = $this->getSearchURL()."?".Toolbox::append_params($options2, '&amp;');

            echo "&nbsp;<a href='$url' title=\"".__s('Processing')."\">(";
            printf(__('%1$s: %2$s'), __('Processing'),
                   $this->countActiveObjectsForUser($options["_users_id_".$typename]));
            echo ")</a>";
         }

         // Display active tickets for a tech
         // Need to update information on dropdown changes
         if ($type == CommonITILActor::ASSIGN) {
            echo "<span id='countassign_$rand'>";
            echo "</span>";

            echo "<script type='text/javascript'>";
            echo "$(function() {";
            Ajax::updateItemJsCode("countassign_$rand",
                                   $CFG_GLPI["root_doc"]."/ajax/ticketassigninformation.php",
                                   ['users_id_assign' => '__VALUE__'],
                                   "dropdown__users_id_".$typename.$rand);
            echo "});</script>";
         }
      }

      if ($CFG_GLPI['notifications_mailing']) {
         echo "<div id='notif_".$typename."_$rand'>";
         echo "</div>";

         echo "<script type='text/javascript'>";
         echo "$(function() {";
         Ajax::updateItemJsCode("notif_".$typename."_$rand",
                                $CFG_GLPI["root_doc"]."/ajax/uemailUpdate.php", $paramscomment,
                                "dropdown_".$actor_name.$rand);
         echo "});</script>";
      }

      return $rand;
   }


   /**
    * show supplier add div on creation
    *
    * @param $options   array    options for default values ($options of showForm)
    *
    * @return nothing display
    **/
   function showSupplierAddFormOnCreate(array $options) {
      global $CFG_GLPI;

      $itemtype = $this->getType();

      echo self::getActorIcon('supplier', 'assign');
      // For ticket templates : mandatories
      if (($itemtype == 'Ticket')
            && isset($options['_tickettemplate'])) {
         echo $options['_tickettemplate']->getMandatoryMark("_suppliers_id_assign");
      }
      echo "&nbsp;";

      $rand   = mt_rand();
      $params = ['name'        => '_suppliers_id_assign',
                      'value'       => $options["_suppliers_id_assign"],
                      'rand'        => $rand];

      if ($CFG_GLPI['notifications_mailing']) {
         $paramscomment = ['value'       => '__VALUE__',
                                'field'       => "_suppliers_id_assign_notif",
                                'allow_email' => true,
                                'typefield'   => 'supplier',
                                'use_notification'
                                    => $options["_suppliers_id_assign_notif"]['use_notification']];
         if (isset($options["_suppliers_id_assign_notif"]['alternative_email'])) {
            $paramscomment['alternative_email']
            = $options["_suppliers_id_assign_notif"]['alternative_email'];
         }
         $params['toupdate'] = ['value_fieldname'
                                                  => 'value',
                                     'to_update'  => "notif_assign_$rand",
                                     'url'        => $CFG_GLPI["root_doc"]."/ajax/uemailUpdate.php",
                                     'moreparams' => $paramscomment];

      }

      if ($itemtype == 'Ticket') {
         $toupdate = [];
         if (isset($params['toupdate']) && is_array($params['toupdate'])) {
            $toupdate[] = $params['toupdate'];
         }
         $toupdate[] = ['value_fieldname' => 'value',
                             'to_update'       => "countassign_$rand",
                             'url'             => $CFG_GLPI["root_doc"].
                                                      "/ajax/ticketassigninformation.php",
                             'moreparams'      => ['suppliers_id_assign' => '__VALUE__']];
         $params['toupdate'] = $toupdate;
      }

      Supplier::dropdown($params);

      if ($itemtype == 'Ticket') {
         // Display active tickets for a tech
         // Need to update information on dropdown changes
         echo "<span id='countassign_$rand'>";
         echo "</span>";
         echo "<script type='text/javascript'>";
         echo "$(function() {";
         Ajax::updateItemJsCode("countassign_$rand",
                                $CFG_GLPI["root_doc"]."/ajax/ticketassigninformation.php",
                                ['suppliers_id_assign' => '__VALUE__'],
                                "dropdown__suppliers_id_assign".$rand);
         echo "});</script>";
      }

      if ($CFG_GLPI['notifications_mailing']) {
         echo "<div id='notif_assign_$rand'>";
         echo "</div>";

         echo "<script type='text/javascript'>";
         echo "$(function() {";
         Ajax::updateItemJsCode("notif_assign_$rand",
                                $CFG_GLPI["root_doc"]."/ajax/uemailUpdate.php", $paramscomment,
                                "dropdown__suppliers_id_assign".$rand);
         echo "});</script>";
      }
   }



   /**
    * show actor part in ITIL object form
    *
    * @param $ID        integer  ITIL object ID
    * @param $options   array    options for default values ($options of showForm)
    *
    * @return nothing display
   **/
   function showActorsPartForm($ID, array $options) {
      global $CFG_GLPI;

      $showuserlink = 0;
      if (User::canView()) {
         $showuserlink = 1;
      }
      $options['_default_use_notification'] = 1;

      if (isset($options['entities_id'])) {
         $options['_default_use_notification'] = Entity::getUsedConfig('is_notif_enable_default', $options['entities_id'], '', 1);
      }

      // check is_hidden fields
      foreach (['_users_id_requester', '_groups_id_requester',
                     '_users_id_observer', '_groups_id_observer',
                     '_users_id_assign', '_groups_id_assign',
                     '_suppliers_id_assign'] as $f) {
         $is_hidden[$f] = false;
         if (isset($options['_tickettemplate'])
             && $options['_tickettemplate']->isHiddenField($f)) {
            $is_hidden[$f] = true;
         }
      }
      $can_admin = $this->canAdminActors();
      // on creation can select actor
      if (!$ID) {
         $can_admin = true;
      }

      $can_assign     = $this->canAssign();
      $can_assigntome = $this->canAssignToMe();

      if (isset($options['_noupdate']) && !$options['_noupdate']) {
         $can_admin       = false;
         $can_assign      = false;
         $can_assigntome  = false;
      }

      // Manage actors
      echo "<div class='tab_actors tab_cadre_fixe' id='mainformtable5'>";
      echo "<div class='responsive_hidden actor_title'>".__('Actor')."</div>";

      // ====== Requesters BLOC ======
      //
      //
      echo "<span class='actor-bloc'>";
      echo "<div class='actor-head'>";
      if (!$is_hidden['_users_id_requester'] || !$is_hidden['_groups_id_requester']) {
         echo __('Requester');
      }
      $rand_requester      = -1;
      $candeleterequester  = false;

      if ($ID
          && $can_admin
          && (!$is_hidden['_users_id_requester'] || !$is_hidden['_groups_id_requester'])
          && !in_array($this->fields['status'], $this->getClosedStatusArray())
      ) {
         $rand_requester = mt_rand();
         echo "&nbsp;";
         echo "<span class='fa fa-plus pointer' title=\"".__s('Add')."\"
                onClick=\"".Html::jsShow("itilactor$rand_requester")."\"
                ><span class='sr-only'>" . __s('Add') . "</span></span>";
         $candeleterequester = true;
      }
      echo "</div>"; // end .actor-head

      echo "<div class='actor-content'>";
      if ($rand_requester >= 0) {
         $this->showActorAddForm(CommonITILActor::REQUESTER, $rand_requester,
                                 $this->fields['entities_id'], $is_hidden);
      }

      // Requester
      if (!$ID) {
         $reqdisplay = false;
         if ($can_admin
             && !$is_hidden['_users_id_requester']) {
            $this->showActorAddFormOnCreate(CommonITILActor::REQUESTER, $options);
            $reqdisplay = true;
         } else {
            $delegating = User::getDelegateGroupsForUser($options['entities_id']);
            if (count($delegating)
                && !$is_hidden['_users_id_requester']) {
               //$this->getDefaultActor(CommonITILActor::REQUESTER);
               $options['_right'] = "delegate";
               $this->showActorAddFormOnCreate(CommonITILActor::REQUESTER, $options);
               $reqdisplay = true;
            } else { // predefined value
               if (isset($options["_users_id_requester"]) && $options["_users_id_requester"]) {
                  echo self::getActorIcon('user', CommonITILActor::REQUESTER)."&nbsp;";
                  echo Dropdown::getDropdownName("glpi_users", $options["_users_id_requester"]);
                  echo "<input type='hidden' name='_users_id_requester' value=\"".
                         $options["_users_id_requester"]."\">";
                  echo '<br>';
                  $reqdisplay=true;
               }
            }
         }

         //If user have access to more than one entity, then display a combobox : Ticket case
         if ($this->userentity_oncreate
             && isset($this->countentitiesforuser)
             && ($this->countentitiesforuser > 1)) {
            echo "<br>";
            $rand = Entity::dropdown(['value'     => $this->fields["entities_id"],
                                           'entity'    => $this->userentities,
                                           'on_change' => 'this.form.submit()']);
         } else {
            echo "<input type='hidden' name='entities_id' value='".$this->fields["entities_id"]."'>";
         }
         if ($reqdisplay) {
            echo '<hr>';
         }

      } else if (!$is_hidden['_users_id_requester']) {
         $this->showUsersAssociated(CommonITILActor::REQUESTER, $candeleterequester, $options);
      }

      // Requester Group
      if (!$ID) {
         if ($can_admin
             && !$is_hidden['_groups_id_requester']) {
            echo self::getActorIcon('group', CommonITILActor::REQUESTER);
            /// For ticket templates : mandatories
            if (isset($options['_tickettemplate'])) {
               echo $options['_tickettemplate']->getMandatoryMark('_groups_id_requester');
            }
            echo "&nbsp;";

            Group::dropdown(['name'      => '_groups_id_requester',
                                  'value'     => $options["_groups_id_requester"],
                                  'entity'    => $this->fields["entities_id"],
                                  'condition' => '`is_requester`']);

         } else { // predefined value
            if (isset($options["_groups_id_requester"]) && $options["_groups_id_requester"]) {
               echo self::getActorIcon('group', CommonITILActor::REQUESTER)."&nbsp;";
               echo Dropdown::getDropdownName("glpi_groups", $options["_groups_id_requester"]);
               echo "<input type='hidden' name='_groups_id_requester' value=\"".
                      $options["_groups_id_requester"]."\">";
               echo '<br>';
            }
         }
      } else if (!$is_hidden['_groups_id_requester']) {
         $this->showGroupsAssociated(CommonITILActor::REQUESTER, $candeleterequester, $options);
      }
      echo "</div>"; // end .actor-content
      echo "</span>"; // end .actor-bloc

      // ====== Observers BLOC ======

      echo "<span class='actor-bloc'>";
      echo "<div class='actor-head'>";
      if (!$is_hidden['_users_id_observer'] || !$is_hidden['_groups_id_observer']) {
         echo __('Watcher');
      }
      $rand_observer       = -1;
      $candeleteobserver   = false;

      if ($ID
          && $can_admin
          && (!$is_hidden['_users_id_observer'] || !$is_hidden['_groups_id_observer'])
          && !in_array($this->fields['status'], $this->getClosedStatusArray())
      ) {
         $rand_observer = mt_rand();

         echo "&nbsp;";
         echo "<span class='fa fa-plus pointer' title=\"".__s('Add')."\"
                onClick=\"".Html::jsShow("itilactor$rand_observer")."\"
                ><span class='sr-only'>" . __s('Add') . "</span></span>";
         $candeleteobserver = true;

      } else if (($ID > 0)
                 && !in_array($this->fields['status'], $this->getClosedStatusArray())
                 && !$is_hidden['_users_id_observer']
                 && !$this->isUser(CommonITILActor::OBSERVER, Session::getLoginUserID())
                 && !$this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())) {
         echo "&nbsp;&nbsp;&nbsp;&nbsp;";
         Html::showSimpleForm($this->getFormURL(), 'addme_observer',
                              __('Associate myself'),
                              [$this->getForeignKeyField() => $this->fields['id']],
                              'fa-male');
      }

      echo "</div>"; // end .actor-head
      echo "<div class='actor-content'>";
      if ($rand_observer >= 0) {
         $this->showActorAddForm(CommonITILActor::OBSERVER, $rand_observer,
                                 $this->fields['entities_id'], $is_hidden);
      }

      // Observer
      if (!$ID) {
         if ($can_admin
             && !$is_hidden['_users_id_observer']) {
            $this->showActorAddFormOnCreate(CommonITILActor::OBSERVER, $options);
            echo '<hr>';
         } else { // predefined value
            if (isset($options["_users_id_observer"][0]) && $options["_users_id_observer"][0]) {
               echo self::getActorIcon('user', CommonITILActor::OBSERVER)."&nbsp;";
               echo Dropdown::getDropdownName("glpi_users", $options["_users_id_observer"][0]);
               echo "<input type='hidden' name='_users_id_observer' value=\"".
                      $options["_users_id_observer"][0]."\">";
               echo '<hr>';
            }
         }
      } else if (!$is_hidden['_users_id_observer']) {
         $this->showUsersAssociated(CommonITILActor::OBSERVER, $candeleteobserver, $options);
      }

      // Observer Group
      if (!$ID) {
         if ($can_admin
             && !$is_hidden['_groups_id_observer']) {
            echo self::getActorIcon('group', CommonITILActor::OBSERVER);
            /// For ticket templates : mandatories
            if (isset($options['_tickettemplate'])) {
               echo $options['_tickettemplate']->getMandatoryMark('_groups_id_observer');
            }
            echo "&nbsp;";

            Group::dropdown(['name'      => '_groups_id_observer',
                                  'value'     => $options["_groups_id_observer"],
                                  'entity'    => $this->fields["entities_id"],
                                  'condition' => '`is_requester`']);
         } else { // predefined value
            if (isset($options["_groups_id_observer"]) && $options["_groups_id_observer"]) {
               echo self::getActorIcon('group', CommonITILActor::OBSERVER)."&nbsp;";
               echo Dropdown::getDropdownName("glpi_groups", $options["_groups_id_observer"]);
               echo "<input type='hidden' name='_groups_id_observer' value=\"".
                      $options["_groups_id_observer"]."\">";
               echo '<br>';
            }
         }
      } else if (!$is_hidden['_groups_id_observer']) {
         $this->showGroupsAssociated(CommonITILActor::OBSERVER, $candeleteobserver, $options);
      }
      echo "</div>"; // end .actor-content
      echo "</span>"; // end .actor-bloc

      // ====== Assign BLOC ======

      echo "<span class='actor-bloc'>";
      echo "<div class='actor-head'>";
      if (!$is_hidden['_users_id_assign']
          || !$is_hidden['_groups_id_assign']
          || !$is_hidden['_suppliers_id_assign']) {
         echo __('Assigned to');
      }
      $rand_assign      = -1;
      $candeleteassign  = false;
      if ($ID
          && ($can_assign || $can_assigntome)
          && (!$is_hidden['_users_id_assign']
              || !$is_hidden['_groups_id_assign']
              || !$is_hidden['_suppliers_id_assign'])
          && $this->isAllowedStatus($this->fields['status'], CommonITILObject::ASSIGNED)) {
         $rand_assign = mt_rand();

         echo "&nbsp;";
         echo "<span class='fa fa-plus pointer' title=\"".__s('Add')."\"
                onClick=\"".Html::jsShow("itilactor$rand_assign")."\"
                ><span class='sr-only'>" . __s('Add') . "</span></span>";
      }
      if ($ID
          && $can_assigntome
          && !in_array($this->fields['status'], $this->getClosedStatusArray())
          && !$is_hidden['_users_id_assign']
          && !$this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
          && $this->isAllowedStatus($this->fields['status'], CommonITILObject::ASSIGNED)) {
         Html::showSimpleForm($this->getFormURL(), 'addme_assign', __('Associate myself'),
                              [$this->getForeignKeyField() => $this->fields['id']],
                              'fa-male');
      }
      if ($ID
          && $can_assign) {
         $candeleteassign = true;
      }
      echo "</div>"; // end .actor-head

      echo "<div class='actor-content'>";
      if ($rand_assign >= 0) {
         $this->showActorAddForm(CommonITILActor::ASSIGN, $rand_assign, $this->fields['entities_id'],
                                 $is_hidden, $this->canAssign(), $this->canAssign());
      }

      // Assign User
      if (!$ID) {
         if ($can_assign
             && !$is_hidden['_users_id_assign']
             && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
            $this->showActorAddFormOnCreate(CommonITILActor::ASSIGN, $options);
            echo '<hr>';

         } else if ($can_assigntome
                    && !$is_hidden['_users_id_assign']
                    && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
            echo self::getActorIcon('user', CommonITILActor::ASSIGN)."&nbsp;";
            User::dropdown(['name'        => '_users_id_assign',
                                 'value'       => $options["_users_id_assign"],
                                 'entity'      => $this->fields["entities_id"],
                                 'ldap_import' => true]);
            echo '<hr>';

         } else { // predefined value
            if (isset($options["_users_id_assign"]) && $options["_users_id_assign"]
                && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
               echo self::getActorIcon('user', CommonITILActor::ASSIGN)."&nbsp;";
               echo Dropdown::getDropdownName("glpi_users", $options["_users_id_assign"]);
               echo "<input type='hidden' name='_users_id_assign' value=\"".
                      $options["_users_id_assign"]."\">";
               echo '<hr>';
            }
         }

      } else if (!$is_hidden['_users_id_assign']) {
         $this->showUsersAssociated(CommonITILActor::ASSIGN, $candeleteassign, $options);
      }

      // Assign Groups
      if (!$ID) {
         if ($can_assign
             && !$is_hidden['_groups_id_assign']
             && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
            echo self::getActorIcon('group', CommonITILActor::ASSIGN);
            /// For ticket templates : mandatories
            if (isset($options['_tickettemplate'])) {
               echo $options['_tickettemplate']->getMandatoryMark('_groups_id_assign');
            }
            echo "&nbsp;";
            $rand   = mt_rand();
            $params = ['name'      => '_groups_id_assign',
                            'value'     => $options["_groups_id_assign"],
                            'entity'    => $this->fields["entities_id"],
                            'condition' => '`is_assign`',
                            'rand'      => $rand];

            if ($this->getType() == 'Ticket') {
               $params['toupdate'] = ['value_fieldname' => 'value',
                                           'to_update'       => "countgroupassign_$rand",
                                           'url'             => $CFG_GLPI["root_doc"].
                                                                "/ajax/ticketassigninformation.php",
                                           'moreparams'      => ['groups_id_assign'
                                                                        => '__VALUE__']];
            }

            Group::dropdown($params);
            echo "<span id='countgroupassign_$rand'>";
            echo "</span>";

            echo "<script type='text/javascript'>";
            echo "$(function() {";
            Ajax::updateItemJsCode("countgroupassign_$rand",
                                   $CFG_GLPI["root_doc"]."/ajax/ticketassigninformation.php",
                                   ['groups_id_assign' => '__VALUE__'],
                                   "dropdown__groups_id_assign$rand");
            echo "});</script>";

            echo '<hr>';
         } else { // predefined value
            if (isset($options["_groups_id_assign"])
                && $options["_groups_id_assign"]
                && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
               echo self::getActorIcon('group', CommonITILActor::ASSIGN)."&nbsp;";
               echo Dropdown::getDropdownName("glpi_groups", $options["_groups_id_assign"]);
               echo "<input type='hidden' name='_groups_id_assign' value=\"".
                      $options["_groups_id_assign"]."\">";
               echo '<hr>';
            }
         }

      } else if (!$is_hidden['_groups_id_assign']) {
         $this->showGroupsAssociated(CommonITILActor::ASSIGN, $candeleteassign, $options);
      }

      // Assign Suppliers
      if (!$ID) {
         if ($can_assign
             && !$is_hidden['_suppliers_id_assign']
             && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
            $this->showSupplierAddFormOnCreate($options);
         } else { // predefined value
            if (isset($options["_suppliers_id_assign"])
                && $options["_suppliers_id_assign"]
                && $this->isAllowedStatus(CommonITILObject::INCOMING, CommonITILObject::ASSIGNED)) {
               echo self::getActorIcon('supplier', CommonITILActor::ASSIGN)."&nbsp;";
               echo Dropdown::getDropdownName("glpi_suppliers", $options["_suppliers_id_assign"]);
               echo "<input type='hidden' name='_suppliers_id_assign' value=\"".
                      $options["_suppliers_id_assign"]."\">";
               echo '<hr>';
            }
         }

      } else if (!$is_hidden['_suppliers_id_assign']) {
         $this->showSuppliersAssociated(CommonITILActor::ASSIGN, $candeleteassign, $options);
      }

      echo "</div>"; // end .actor-content
      echo "</span>"; // end .actor-bloc

      echo "</div>"; // tab_actors
   }


   /**
    * @param $actiontime
   **/
   static function getActionTime($actiontime) {
      return Html::timestampToString($actiontime, false);
   }


   /**
    * @param $ID
    * @param $itemtype
    * @param $link      (default 0)
   **/
   static function getAssignName($ID, $itemtype, $link = 0) {

      switch ($itemtype) {
         case 'User' :
            if ($ID == 0) {
               return "";
            }
            return getUserName($ID, $link);

         case 'Supplier' :
         case 'Group' :
            $item = new $itemtype();
            if ($item->getFromDB($ID)) {
               $before = "";
               $after  = "";
               if ($link) {
                  return $item->getLink(['comments' => true]);
               }
               return $item->getNameID();
            }
            return "";
      }
   }


   /**
    * Form to add a solution to an ITIL object
    *
    * @param $knowbase_id_toload integer  load a kb article as solution (0 = no load by default)
    *                                     (default 0)
   **/
   function showSolutions($knowbase_id_toload = 0) {
      global $CFG_GLPI;

      $solution = new ITILSolution();
      $solution->showSummary($this);
      if (ITILSolution::countFor($this->getType(), $this->getID()) > 0) {
         $rand = mt_rand();
         Html::file(['editor_id' => "solution$rand",
                     'showtitle' => false,
                     'multiple' => true]);
      } else {
         $solution->showForm(null, ['item' => $this]);
      }
   }

   /**
    * Form to add a solution to an ITIL object
    *
    * @since 0.84
    * @since 9.2 Signature has changed
    *
    * @param CommonITILObject $item item instance
    *
    * @param $entities_id
   **/
   static function showMassiveSolutionForm(CommonITILObject $item) {
      echo "<table class='tab_cadre_fixe'>";
      echo '<tr><th colspan=4>'.__('Solve tickets').'</th></tr>';

      $solution = new ITILSolution();
      $solution->showForm(
         null,
         [
            'item'   => $item,
            'entity' => $item->getEntityID(),
            'noform' => true,
            'nokb'   => true
         ]
      );

      echo "</td></tr>";
      echo '</table>';
   }


   /**
    * Update date mod of the ITIL object
    *
    * @param $ID                    integer  ID of the ITIL object
    * @param $no_stat_computation   boolean  do not cumpute take into account stat (false by default)
    * @param $users_id_lastupdater  integer  to force last_update id (default 0 = not used)
   **/
   function updateDateMod($ID, $no_stat_computation = false, $users_id_lastupdater = 0) {
      global $DB;

      if ($this->getFromDB($ID)) {
         // Force date mod and lastupdater
         $update = ['date_mod' => $_SESSION['glpi_currenttime']];

         // set last updater if interactive user
         if (!Session::isCron()) {
            $update['users_id_lastupdater'] = Session::getLoginUserID();
         } else if ($users_id_lastupdater > 0) {
            $update['users_id_lastupdater'] = $users_id_lastupdater;
         }

         $DB->update(
            $this->getTable(),
            $update,
            ['id' => $ID]
         );
      }
   }


   /**
    * Update actiontime of the object based on actiontime of the tasks
    *
    * @param $ID ID of the object
    *
    * @return boolean : success
   **/
   function updateActionTime($ID) {
      global $DB;

      $tot       = 0;
      $tasktable = getTableForItemType($this->getType().'Task');

      $query = "SELECT SUM(`actiontime`)
                FROM `$tasktable`
                WHERE `".$this->getForeignKeyField()."` = '$ID'";

      if ($result = $DB->query($query)) {
         $sum = $DB->result($result, 0, 0);
         if (!is_null($sum)) {
            $tot += $sum;
         }
      }

      $result = $DB->update(
         $this->getTable(), [
            'actiontime' => $tot
         ], [
            'id' => $ID
         ]
      );
      return $result;
   }


   /**
    * Get all available types to which an ITIL object can be assigned
   **/
   static function getAllTypesForHelpdesk() {
      global $PLUGIN_HOOKS, $CFG_GLPI;

      /// TODO ticket_types -> itil_types

      $types = [];
      $ptypes = [];
      //Types of the plugins (keep the plugin hook for right check)
      if (isset($PLUGIN_HOOKS['assign_to_ticket'])) {
         foreach ($PLUGIN_HOOKS['assign_to_ticket'] as $plugin => $value) {
            $ptypes = Plugin::doOneHook($plugin, 'AssignToTicket', $ptypes);
         }
      }
      asort($ptypes);
      //Types of the core (after the plugin for robustness)
      foreach ($CFG_GLPI["ticket_types"] as $itemtype) {
         if ($item = getItemForItemtype($itemtype)) {
            if (!isPluginItemType($itemtype) // No plugin here
                && in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
               $types[$itemtype] = $item->getTypeName(1);
            }
         }
      }
      asort($types); // core type first... asort could be better ?

      // Drop not available plugins
      foreach ($ptypes as $itemtype => $itemtype_name) {
         if (!in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
            unset($ptypes[$itemtype]);
         }
      }

      $types = array_merge($types, $ptypes);
      return $types;
   }


   /**
    * Check if it's possible to assign ITIL object to a type (core or plugin)
    *
    * @param $itemtype the object's type
    *
    * @return true if ticket can be assign to this type, false if not
   **/
   static function isPossibleToAssignType($itemtype) {
      global $PLUGIN_HOOKS;

      // TODO : assign_to_ticket to assign_to_itil
      // Plugin case
      // if ($plug = isPluginItemType($itemtype)) {
      //    //If it's not a core's type, then check plugins
      //    $types = array();
      //    if (isset($PLUGIN_HOOKS['assign_to_ticket'])) {
      //       $types = Plugin::doOneHook($plug['plugin'], 'AssignToTicket', $types);
      //       if (array_key_exists($itemtype,$types)) {
      //          return true;
      //       }
      //    }
      // // standard case
      // } else {
      //    if (in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
      //       return true;
      //    }
      // }
      if (in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
         return true;
      }
      return false;
   }


   /**
    * Compute solve delay stat of the current ticket
   **/
   function computeSolveDelayStat() {

      if (isset($this->fields['id'])
          && !empty($this->fields['date'])
          && !empty($this->fields['solvedate'])) {

         $calendars_id = $this->getCalendar();
         $calendar     = new Calendar();

         // Using calendar
         if (($calendars_id > 0)
             && $calendar->getFromDB($calendars_id)) {
            return max(0, $calendar->getActiveTimeBetween($this->fields['date'],
                                                          $this->fields['solvedate'])
                                                            -$this->fields["waiting_duration"]);
         }
         // Not calendar defined
         return max(0, strtotime($this->fields['solvedate'])-strtotime($this->fields['date'])
                       -$this->fields["waiting_duration"]);
      }
      return 0;
   }


   /**
    * Compute close delay stat of the current ticket
   **/
   function computeCloseDelayStat() {

      if (isset($this->fields['id'])
          && !empty($this->fields['date'])
          && !empty($this->fields['closedate'])) {

         $calendars_id = $this->getCalendar();
         $calendar     = new Calendar();

         // Using calendar
         if (($calendars_id > 0)
             && $calendar->getFromDB($calendars_id)) {
            return max(0, $calendar->getActiveTimeBetween($this->fields['date'],
                                                          $this->fields['closedate'])
                                                             -$this->fields["waiting_duration"]);
         }
         // Not calendar defined
         return max(0, strtotime($this->fields['closedate'])-strtotime($this->fields['date'])
                       -$this->fields["waiting_duration"]);
      }
      return 0;
   }


   function showStats() {

      if (!$this->canView()
          || !isset($this->fields['id'])) {
         return false;
      }

      $this->showStatsDates();
      Plugin::doHook('show_item_stats', $this);
      $this->showStatsTimes();
   }

   function showStatsDates() {
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>"._n('Date', 'Dates', Session::getPluralNumber())."</th></tr>";

      echo "<tr class='tab_bg_2'><td>".__('Opening date')."</td>";
      echo "<td>".Html::convDateTime($this->fields['date'])."</td></tr>";

      echo "<tr class='tab_bg_2'><td>".__('Time to resolve')."</td>";
      echo "<td>".Html::convDateTime($this->fields['time_to_resolve'])."</td></tr>";

      if (in_array($this->fields['status'], array_merge($this->getSolvedStatusArray(),
                                                        $this->getClosedStatusArray()))) {
         echo "<tr class='tab_bg_2'><td>".__('Resolution date')."</td>";
         echo "<td>".Html::convDateTime($this->fields['solvedate'])."</td></tr>";
      }

      if (in_array($this->fields['status'], $this->getClosedStatusArray())) {
         echo "<tr class='tab_bg_2'><td>".__('Closing date')."</td>";
         echo "<td>".Html::convDateTime($this->fields['closedate'])."</td></tr>";
      }
      echo "</table>";
   }

   function showStatsTimes() {
      echo "<div class='dates_timelines'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>"._n('Time', 'Times', Session::getPluralNumber())."</th></tr>";

      if (isset($this->fields['takeintoaccount_delay_stat'])) {
         echo "<tr class='tab_bg_2'><td>".__('Take into account')."</td><td>";
         if ($this->fields['takeintoaccount_delay_stat'] > 0) {
            echo Html::timestampToString($this->fields['takeintoaccount_delay_stat'], 0);
         } else {
            echo '&nbsp;';
         }
         echo "</td></tr>";
      }

      if (in_array($this->fields['status'], array_merge($this->getSolvedStatusArray(),
                                                        $this->getClosedStatusArray()))) {
         echo "<tr class='tab_bg_2'><td>".__('Resolution')."</td><td>";

         if ($this->fields['solve_delay_stat'] > 0) {
            echo Html::timestampToString($this->fields['solve_delay_stat'], 0);
         } else {
            echo '&nbsp;';
         }
         echo "</td></tr>";
      }

      if (in_array($this->fields['status'], $this->getClosedStatusArray())) {
         echo "<tr class='tab_bg_2'><td>".__('Closure')."</td><td>";
         if ($this->fields['close_delay_stat'] > 0) {
            echo Html::timestampToString($this->fields['close_delay_stat']);
         } else {
            echo '&nbsp;';
         }
         echo "</td></tr>";
      }

      echo "<tr class='tab_bg_2'><td>".__('Pending')."</td><td>";
      if ($this->fields['waiting_duration'] > 0) {
         echo Html::timestampToString($this->fields['waiting_duration'], 0);
      } else {
         echo '&nbsp;';
      }
      echo "</td></tr>";

      echo "</table>";
      echo "</div>";
   }


   /** Get users_ids of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct users_ids which have itil object
   **/
   function getUsedAuthorBetween($date1 = '', $date2 = '') {
      global $DB;

      $linkclass = new $this->userlinkclass();
      $linktable = $linkclass->getTable();

      $query = "SELECT DISTINCT `glpi_users`.`id` AS users_id, `glpi_users`.`name` AS name,
                                `glpi_users`.`realname` AS realname,
                                `glpi_users`.`firstname` AS firstname
                FROM `".$this->getTable()."`
                LEFT JOIN `$linktable`
                  ON (`$linktable`.`".$this->getForeignKeyField()."` = `".$this->getTable()."`.`id`
                      AND `$linktable`.`type` = '".CommonITILActor::REQUESTER."')
                INNER JOIN `glpi_users` ON (`glpi_users`.`id` = `$linktable`.`users_id`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY realname, firstname, name";

      $result = $DB->query($query);
      $tab    = [];
      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["users_id"];
            $tmp['link'] = formatUserName($line["users_id"], $line["name"], $line["realname"],
                                          $line["firstname"], 1);
            $tab[] = $tmp;
         }
      }
      return $tab;
   }


   /** Get recipient of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct recipents which have itil object
   **/
   function getUsedRecipientBetween($date1 = '', $date2 = '') {
      global $DB;

      $query = "SELECT DISTINCT `glpi_users`.`id` AS user_id,
                                `glpi_users`.`name` AS name,
                                `glpi_users`.`realname` AS realname,
                                `glpi_users`.`firstname` AS firstname
                FROM `".$this->getTable()."`
                LEFT JOIN `glpi_users`
                     ON (`glpi_users`.`id` = `".$this->getTable()."`.`users_id_recipient`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY realname, firstname, name";

      $result = $DB->query($query);
      $tab    = [];

      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["user_id"];
            $tmp['link'] = formatUserName($line["user_id"], $line["name"], $line["realname"],
                                          $line["firstname"], 1);
            $tab[] = $tmp;
         }
      }
      return $tab;
   }


   /** Get groups which have itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct groups of tickets
   **/
   function getUsedGroupBetween($date1 = '', $date2 = '') {
      global $DB;

      $linkclass = new $this->grouplinkclass();
      $linktable = $linkclass->getTable();

      $query = "SELECT DISTINCT `glpi_groups`.`id`, `glpi_groups`.`completename`
                FROM `".$this->getTable()."`
                LEFT JOIN `$linktable`
                  ON (`$linktable`.`".$this->getForeignKeyField()."` = `".$this->getTable()."`.`id`
                      AND `$linktable`.`type` = '".CommonITILActor::REQUESTER."')
                LEFT JOIN `glpi_groups` ON (`$linktable`.`groups_id` = `glpi_groups`.`id`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY `glpi_groups`.`completename`";

      $result = $DB->query($query);
      $tab    = [];

      if ($DB->numrows($result) >=1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["id"];
            $tmp['link'] = $line["completename"];
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /** Get recipient of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    * @param title       : indicates if stat if by title (true) or type (false) (true by default)
    *
    * @return array contains the distinct recipents which have tickets
   **/
   function getUsedUserTitleOrTypeBetween($date1 = '', $date2 = '', $title = true) {
      global $DB;

      $linkclass = new $this->userlinkclass();
      $linktable = $linkclass->getTable();

      if ($title) {
         $table = "glpi_usertitles";
         $field = "usertitles_id";
      } else {
         $table = "glpi_usercategories";
         $field = "usercategories_id";
      }

      $query = "SELECT DISTINCT `glpi_users`.`$field`
                FROM `".$this->getTable()."`
                INNER JOIN `$linktable`
                  ON (`".$this->getTable()."`.`id` = `$linktable`.`".$this->getForeignKeyField()."`)
                INNER JOIN `glpi_users` ON (`glpi_users`.`id` = `$linktable`.`users_id`)
                LEFT JOIN `$table` ON (`$table`.`id` = `glpi_users`.`$field`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1)||!empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .=" ORDER BY `glpi_users`.`$field`";

      $result = $DB->query($query);
      $tab    = [];
      if ($DB->numrows($result) >=1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line[$field];
            $tmp['link'] = Dropdown::getDropdownName($table, $line[$field]);
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /**
    * Get priorities of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct priorities of tickets
   **/
   function getUsedPriorityBetween($date1 = '', $date2 = '') {
      global $DB;

      $query = "SELECT DISTINCT `priority`
                FROM `".$this->getTable()."`
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY `priority`";

      $result = $DB->query($query);
      $tab    = [];
      if ($DB->numrows($result) >= 1) {
         $i = 0;
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["priority"];
            $tmp['link'] = self::getPriorityName($line["priority"]);
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /**
    * Get urgencies of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct priorities of tickets
   **/
   function getUsedUrgencyBetween($date1 = '', $date2 = '') {
      global $DB;

      $query = "SELECT DISTINCT `urgency`
                FROM `".$this->getTable()."`
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY `urgency`";

      $result = $DB->query($query);
      $tab    = [];

      if ($DB->numrows($result) >= 1) {
         $i = 0;
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["urgency"];
            $tmp['link'] = self::getUrgencyName($line["urgency"]);
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /**
    * Get impacts of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct priorities of tickets
   **/
   function getUsedImpactBetween($date1 = '', $date2 = '') {
      global $DB;

      $query = "SELECT DISTINCT `impact`
                FROM `".$this->getTable()."`
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY `impact`";
      $result = $DB->query($query);
      $tab    = [];

      if ($DB->numrows($result) >= 1) {
         $i = 0;
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["impact"];
            $tmp['link'] = self::getImpactName($line["impact"]);
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /**
    * Get request types of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct request types of tickets
   **/
   function getUsedRequestTypeBetween($date1 = '', $date2 = '') {
      global $DB;

      $query = "SELECT DISTINCT `requesttypes_id`
                FROM `".$this->getTable()."`
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`",
                                           $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`",
                                              $date1, $date2).") ";
      }
      $query .= " ORDER BY `requesttypes_id`";

      $result = $DB->query($query);
      $tab    = [];
      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["requesttypes_id"];
            $tmp['link'] = Dropdown::getDropdownName('glpi_requesttypes',
                                                     $line["requesttypes_id"]);
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /**
    * Get solution types of itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct request types of tickets
   **/
   function getUsedSolutionTypeBetween($date1 = '', $date2 = '') {
      global $DB;

      $query = "SELECT DISTINCT `solutiontypes_id`
                FROM `".ITILSolution::getTable()."`
                INNER JOIN `".$this->getTable()."`
                  ON `".$this->getTable().".`id` = `".ITILSolution::getTable()."`.`items_id`
                WHERE `".ITILSolution::getTable()."`.`itemtype`='".$this->getType()."'
                  AND `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY `solutiontypes_id`";

      $result = $DB->query($query);
      $tab    = [];
      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["solutiontypes_id"];
            $tmp['link'] = Dropdown::getDropdownName('glpi_solutiontypes',
                                                     $line["solutiontypes_id"]);
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /** Get users which have intervention assigned to  between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct users which have any intervention assigned to.
   **/
   function getUsedTechBetween($date1 = '', $date2 = '') {
      global $DB;

      $linkclass = new $this->userlinkclass();
      $linktable = $linkclass->getTable();
      $showlink = User::canView();

      $query = "SELECT DISTINCT `glpi_users`.`id` AS users_id,
                                `glpi_users`.`name` AS name,
                                `glpi_users`.`realname` AS realname,
                                `glpi_users`.`firstname` AS firstname
                FROM `".$this->getTable()."`
                LEFT JOIN `$linktable`
                  ON (`$linktable`.`".$this->getForeignKeyField()."` = `".$this->getTable()."`.`id`
                      AND `$linktable`.`type` = '".CommonITILActor::ASSIGN."')
                LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `$linktable`.`users_id`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1)||!empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`",
                                           $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`",
                                              $date1, $date2).") ";
      }
      $query .= " ORDER BY realname, firstname, name";

      $result = $DB->query($query);
      $tab    = [];

      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["users_id"];
            $tmp['link'] = formatUserName($line["users_id"], $line["name"],
                                          $line["realname"],
                                          $line["firstname"], $showlink);
            $tab[] = $tmp;
         }
      }
      return $tab;
   }


   /** Get users which have followup assigned to  between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct users which have any followup assigned to.
   **/
   function getUsedTechTaskBetween($date1 = '', $date2 = '') {
      global $DB;

      $tasktable = getTableForItemType($this->getType().'Task');
      $showlink = User::canView();

      $query = "SELECT DISTINCT `glpi_users`.`id` AS users_id,
                                `glpi_users`.`name` AS name,
                                `glpi_users`.`realname` AS realname,
                                `glpi_users`.`firstname` AS firstname
                FROM `".$this->getTable()."`
                LEFT JOIN `$tasktable`
                  ON (`".$this->getTable()."`.`id` = `$tasktable`.`".$this->getForeignKeyField()."`)
                LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `$tasktable`.`users_id`)
                LEFT JOIN `glpi_profiles_users`
                  ON (`glpi_users`.`id` = `glpi_profiles_users`.`users_id`)
                LEFT JOIN `glpi_profiles`
                  ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`)
                LEFT JOIN `glpi_profilerights`
                  ON (`glpi_profiles`.`id` = `glpi_profilerights`.`profiles_id`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`",
                                           $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`",
                                              $date1, $date2).") ";
      }
      $query .="     AND `glpi_profilerights`.`name` = 'ticket'
                     AND (`glpi_profilerights`.`rights` & ". Ticket::OWN.")
                     AND `$tasktable`.`users_id` <> '0'
                     AND `$tasktable`.`users_id` IS NOT NULL
               ORDER BY realname, firstname, name";

      $result = $DB->query($query);
      $tab    = [];

      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["users_id"];
            $tmp['link'] = formatUserName($line["users_id"], $line["name"],
                                          $line["realname"],
                                          $line["firstname"], $showlink);
            $tab[] = $tmp;
         }
      }
      return $tab;
   }


   /** Get enterprises which have itil object assigned to between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct enterprises which have any tickets assigned to.
   **/
   function getUsedSupplierBetween($date1 = '', $date2 = '') {
      global $DB,$CFG_GLPI;

      $linkclass = new $this->supplierlinkclass();
      $linktable = $linkclass->getTable();

      $query = "SELECT DISTINCT `glpi_suppliers`.`id` AS suppliers_id_assign,
                                `glpi_suppliers`.`name` AS name
                FROM `".$this->getTable()."`
                LEFT JOIN `$linktable`
                  ON (`$linktable`.`".$this->getForeignKeyField()."` = `".$this->getTable()."`.`id`
                      AND `$linktable`.`type` = '".CommonITILActor::ASSIGN."')
                LEFT JOIN `glpi_suppliers`
                     ON (`glpi_suppliers`.`id` = `$linktable`.`suppliers_id`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`",
                                           $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`",
                                              $date1, $date2).") ";
      }
      $query .= " ORDER BY name";

      $tab    = [];
      $result = $DB->query($query);
      if ($DB->numrows($result) > 0) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp["id"]   = $line["suppliers_id_assign"];
            $tmp["link"] = "<a href='".Supplier::getFormURLWithID($line["suppliers_id_assign"])."'>".$line["name"]."</a>";
            $tab[] = $tmp;
         }
      }
      return $tab;
   }


   /** Get groups assigned to itil object between 2 dates
    *
    * @param $date1 date : begin date (default '')
    * @param $date2 date : end date (default '')
    *
    * @return array contains the distinct groups assigned to a tickets
   **/
   function getUsedAssignGroupBetween($date1 = '', $date2 = '') {
      global $DB;

      $linkclass = new $this->grouplinkclass();
      $linktable = $linkclass->getTable();

      $query = "SELECT DISTINCT `glpi_groups`.`id`, `glpi_groups`.`completename`
                FROM `".$this->getTable()."`
                LEFT JOIN `$linktable`
                  ON (`$linktable`.`".$this->getForeignKeyField()."` = `".$this->getTable()."`.`id`
                      AND `$linktable`.`type` = '".CommonITILActor::ASSIGN."')
                LEFT JOIN `glpi_groups` ON (`$linktable`.`groups_id` = `glpi_groups`.`id`)
                WHERE `".$this->getTable()."`.`is_deleted` = 0 ".
                      getEntitiesRestrictRequest("AND", $this->getTable());

      if (!empty($date1) || !empty($date2)) {
         $query .= " AND (".getDateRequest("`".$this->getTable()."`.`date`", $date1, $date2)."
                          OR ".getDateRequest("`".$this->getTable()."`.`closedate`", $date1,
                                              $date2).") ";
      }
      $query .= " ORDER BY `glpi_groups`.`completename`";

      $result = $DB->query($query);
      $tab    = [];
      if ($DB->numrows($result) >= 1) {
         while ($line = $DB->fetch_assoc($result)) {
            $tmp['id']   = $line["id"];
            $tmp['link'] = $line["completename"];
            $tab[]       = $tmp;
         }
      }
      return $tab;
   }


   /**
    * Display a line for an object
    *
    * @since 0.85 (befor in each object with differents parameters)
    *
    * @param $id                 Integer  ID of the object
    * @param $options            array of options
    *      output_type            : Default output type (see Search class / default Search::HTML_OUTPUT)
    *      row_num                : row num used for display
    *      type_for_massiveaction : itemtype for massive action
    *      id_for_massaction      : default 0 means no massive action
    *      followups              : only for Tickets : show followup columns
    */
   static function showShort($id, $options = []) {
      global $CFG_GLPI, $DB;

      $p['output_type']            = Search::HTML_OUTPUT;
      $p['row_num']                = 0;
      $p['type_for_massiveaction'] = 0;
      $p['id_for_massiveaction']   = 0;
      $p['followups']              = false;

      if (count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $rand = mt_rand();

      /// TODO to be cleaned. Get datas and clean display links

      // Prints a job in short form
      // Should be called in a <table>-segment
      // Print links or not in case of user view
      // Make new job object and fill it from database, if success, print it
      $item         = new static();

      $candelete   = static::canDelete();
      $canupdate   = Session::haveRight(static::$rightname, UPDATE);
      $showprivate = Session::haveRight('followup', TicketFollowup::SEEPRIVATE);
      $align       = "class='center";
      $align_desc  = "class='left";

      if ($p['followups']) {
         $align      .= " top'";
         $align_desc .= " top'";
      } else {
         $align      .= "'";
         $align_desc .= "'";
      }

      if ($item->getFromDB($id)) {
         $item_num = 1;
         $bgcolor  = $_SESSION["glpipriority_".$item->fields["priority"]];

         echo Search::showNewLine($p['output_type'], $p['row_num']%2);

         $check_col = '';
         if (($candelete || $canupdate)
             && ($p['output_type'] == Search::HTML_OUTPUT)
             && $p['id_for_massiveaction']) {

            $check_col = Html::getMassiveActionCheckBox($p['type_for_massiveaction'], $p['id_for_massiveaction']);
         }
         echo Search::showItem($p['output_type'], $check_col, $item_num, $p['row_num'], $align);

         // First column
         $first_col = sprintf(__('%1$s: %2$s'), __('ID'), $item->fields["id"]);
         if ($p['output_type'] == Search::HTML_OUTPUT) {
            $first_col .= static::getStatusIcon($item->fields["status"]);
         } else {
            $first_col = sprintf(__('%1$s - %2$s'), $first_col,
                                 static::getStatus($item->fields["status"]));
         }

         echo Search::showItem($p['output_type'], $first_col, $item_num, $p['row_num'], $align);

         // Second column
         if ($item->fields['status'] == static::CLOSED) {
            $second_col = sprintf(__('Closed on %s'),
                                  ($p['output_type'] == Search::HTML_OUTPUT?'<br>':'').
                                    Html::convDateTime($item->fields['closedate']));
         } else if ($item->fields['status'] == static::SOLVED) {
            $second_col = sprintf(__('Solved on %s'),
                                  ($p['output_type'] == Search::HTML_OUTPUT?'<br>':'').
                                    Html::convDateTime($item->fields['solvedate']));
         } else if ($item->fields['begin_waiting_date']) {
            $second_col = sprintf(__('Put on hold on %s'),
                                  ($p['output_type'] == Search::HTML_OUTPUT?'<br>':'').
                                    Html::convDateTime($item->fields['begin_waiting_date']));
         } else if ($item->fields['time_to_resolve']) {
            $second_col = sprintf(__('%1$s: %2$s'), __('Time to resolve'),
                                  ($p['output_type'] == Search::HTML_OUTPUT?'<br>':'').
                                    Html::convDateTime($item->fields['time_to_resolve']));
         } else {
            $second_col = sprintf(__('Opened on %s'),
                                  ($p['output_type'] == Search::HTML_OUTPUT?'<br>':'').
                                    Html::convDateTime($item->fields['date']));
         }

         echo Search::showItem($p['output_type'], $second_col, $item_num, $p['row_num'], $align." width=130");

         // Second BIS column
         $second_col = Html::convDateTime($item->fields["date_mod"]);
         echo Search::showItem($p['output_type'], $second_col, $item_num, $p['row_num'], $align." width=90");

         // Second TER column
         if (count($_SESSION["glpiactiveentities"]) > 1) {
            $second_col = Dropdown::getDropdownName('glpi_entities', $item->fields['entities_id']);
            echo Search::showItem($p['output_type'], $second_col, $item_num, $p['row_num'],
                                  $align." width=100");
         }

         // Third Column
         echo Search::showItem($p['output_type'],
                               "<span class='b'>".static::getPriorityName($item->fields["priority"]).
                                 "</span>",
                               $item_num, $p['row_num'], "$align bgcolor='$bgcolor'");

         // Fourth Column
         $fourth_col = "";

         foreach ($item->getUsers(CommonITILActor::REQUESTER) as $d) {
            $userdata    = getUserName($d["users_id"], 2);
            $fourth_col .= sprintf(__('%1$s %2$s'),
                                    "<span class='b'>".$userdata['name']."</span>",
                                    Html::showToolTip($userdata["comment"],
                                                      ['link'    => $userdata["link"],
                                                            'display' => false]));
            $fourth_col .= "<br>";
         }

         foreach ($item->getGroups(CommonITILActor::REQUESTER) as $d) {
            $fourth_col .= Dropdown::getDropdownName("glpi_groups", $d["groups_id"]);
            $fourth_col .= "<br>";
         }

         echo Search::showItem($p['output_type'], $fourth_col, $item_num, $p['row_num'], $align);

         // Fifth column
         $fifth_col = "";

         foreach ($item->getUsers(CommonITILActor::ASSIGN) as $d) {
            $userdata   = getUserName($d["users_id"], 2);
            $fifth_col .= sprintf(__('%1$s %2$s'),
                                    "<span class='b'>".$userdata['name']."</span>",
                                    Html::showToolTip($userdata["comment"],
                                                      ['link'    => $userdata["link"],
                                                            'display' => false]));
            $fifth_col .= "<br>";
         }

         foreach ($item->getGroups(CommonITILActor::ASSIGN) as $d) {
            $fifth_col .= Dropdown::getDropdownName("glpi_groups", $d["groups_id"]);
            $fifth_col .= "<br>";
         }

         foreach ($item->getSuppliers(CommonITILActor::ASSIGN) as $d) {
            $fifth_col .= Dropdown::getDropdownName("glpi_suppliers", $d["suppliers_id"]);
            $fifth_col .= "<br>";
         }

         echo Search::showItem($p['output_type'], $fifth_col, $item_num, $p['row_num'], $align);

         // Sixth Colum
         // Ticket : simple link to item
         $sixth_col  = "";
         $is_deleted = false;
         $item_ticket = new Item_Ticket();
         $data = $item_ticket->find("`tickets_id` = ".$item->fields['id']);

         if ($item->getType() == 'Ticket') {
            if (!empty($data)) {
               foreach ($data as $val) {
                  if (!empty($val["itemtype"]) && ($val["items_id"] > 0)) {
                     if ($object = getItemForItemtype($val["itemtype"])) {
                        if ($object->getFromDB($val["items_id"])) {
                           $is_deleted = $object->isDeleted();

                           $sixth_col .= $object->getTypeName();
                           $sixth_col .= " - <span class='b'>";
                           if ($item->canView()) {
                              $sixth_col .= $object->getLink();
                           } else {
                              $sixth_col .= $object->getNameID();
                           }
                           $sixth_col .= "</span><br>";
                        }
                     }
                  }
               }
            } else {
               $sixth_col = __('General');
            }

            echo Search::showItem($p['output_type'], $sixth_col, $item_num, $p['row_num'], ($is_deleted ? " class='center deleted' " : $align));
         }

         // Seventh column
         echo Search::showItem($p['output_type'],
                               "<span class='b'>".
                                 Dropdown::getDropdownName('glpi_itilcategories',
                                                           $item->fields["itilcategories_id"]).
                               "</span>",
                               $item_num, $p['row_num'], $align);

         // Eigth column
         $eigth_column = "<span class='b'>".$item->getName()."</span>&nbsp;";

         // Add link
         if ($item->canViewItem()) {
            $eigth_column = "<a id='".$item->getType().$item->fields["id"]."$rand' href=\"".$item->getLinkURL()
                              ."\">$eigth_column</a>";

            if ($p['followups']
                && ($p['output_type'] == Search::HTML_OUTPUT)) {
               $eigth_column .= TicketFollowup::showShortForTicket($item->fields["id"]);
            } else {
               if (method_exists($item, 'numberOfFollowups')) {
                  $eigth_column  = sprintf(__('%1$s (%2$s)'), $eigth_column,
                                          sprintf(__('%1$s - %2$s'),
                                                   $item->numberOfFollowups($showprivate),
                                                   $item->numberOfTasks($showprivate)));
               } else {
                  $eigth_column  = sprintf(__('%1$s (%2$s)'), $eigth_column,
                                                   $item->numberOfTasks($showprivate));

               }
            }
         }

         if ($p['output_type'] == Search::HTML_OUTPUT) {
            $eigth_column = sprintf(__('%1$s %2$s'), $eigth_column,
                                    Html::showToolTip(Html::clean(Html::entity_decode_deep($item->fields["content"])),
                                                      ['display' => false,
                                                            'applyto' => $item->getType().$item->fields["id"].
                                                                           $rand]));
         }

         echo Search::showItem($p['output_type'], $eigth_column, $item_num, $p['row_num'],
                               $align_desc." width='200'");

         //tenth column
         $tenth_column  = '';
         $planned_infos = '';

         $tasktype      = $item->getType()."Task";
         $plan          = new $tasktype();
         $items         = [];

         foreach ($DB->request($plan->getTable(),
                               [$item->getForeignKeyField() => $item->fields['id']]) as $plan) {

            if (isset($plan['begin']) && $plan['begin']) {
               $items[$plan['id']] = $plan['id'];
               $planned_infos .= sprintf(__('From %s').
                                            ($p['output_type'] == Search::HTML_OUTPUT?'<br>':''),
                                         Html::convDateTime($plan['begin']));
               $planned_infos .= sprintf(__('To %s').
                                            ($p['output_type'] == Search::HTML_OUTPUT?'<br>':''),
                                         Html::convDateTime($plan['end']));
               if ($plan['users_id_tech']) {
                  $planned_infos .= sprintf(__('By %s').
                                               ($p['output_type'] == Search::HTML_OUTPUT?'<br>':''),
                                            getUserName($plan['users_id_tech']));
               }
               $planned_infos .= "<br>";
            }

         }
         unset($i, $j);

         $tenth_column = count($items);
         if ($tenth_column) {
            $tenth_column = "<span class='pointer'
                              id='".$item->getType().$item->fields["id"]."planning$rand'>".
                              $tenth_column.'</span>';
            $tenth_column = sprintf(__('%1$s %2$s'), $tenth_column,
                                    Html::showToolTip($planned_infos,
                                                      ['display' => false,
                                                            'applyto' => $item->getType().
                                                                           $item->fields["id"].
                                                                           "planning".$rand]));
         }
         echo Search::showItem($p['output_type'], $tenth_column, $item_num, $p['row_num'],
                               $align_desc." width='150'");

         // Finish Line
         echo Search::showEndLine($p['output_type']);
      } else {
         echo "<tr class='tab_bg_2'>";
         echo "<td colspan='6' ><i>".__('No item in progress.')."</i></td></tr>";
      }
   }

   /**
    * @param $output_type     (default 'Search::HTML_OUTPUT')
    * @param $mass_id         id of the form to check all (default '')
    */
   static function commonListHeader($output_type = Search::HTML_OUTPUT, $mass_id = '') {

      // New Line for Header Items Line
      echo Search::showNewLine($output_type);
      // $show_sort if
      $header_num                      = 1;

      $items                           = [];
      $items[(empty($mass_id)?'&nbsp':Html::getCheckAllAsCheckbox($mass_id))] = '';
      $items[__('Status')]             = "status";
      $items[__('Date')]               = "date";
      $items[__('Last update')]        = "date_mod";

      if (count($_SESSION["glpiactiveentities"]) > 1) {
         $items[_n('Entity', 'Entities', Session::getPluralNumber())] = "glpi_entities.completename";
      }

      $items[__('Priority')]           = "priority";
      $items[__('Requester')]          = "users_id";
      $items[__('Assigned')]           = "users_id_assign";
      if (static::getType() == 'Ticket') {
         $items[_n('Associated element', 'Associated elements', Session::getPluralNumber())] = "";
      }
      $items[__('Category')]           = "glpi_itilcategories.completename";
      $items[__('Title')]              = "name";
      $items[__('Planification')]      = "glpi_tickettasks.begin";

      foreach ($items as $key => $val) {
         $issort = 0;
         $link   = "";
         echo Search::showHeaderItem($output_type, $key, $header_num, $link);
      }

      // End Line for column headers
      echo Search::showEndLine($output_type);
   }


   /**
    * Get correct Calendar: Entity or Sla
    *
    * @since 0.90.4
    *
    **/
   function getCalendar() {
      return Entity::getUsedConfig('calendars_id', $this->fields['entities_id']);
   }


   /**
    * Summary of getTimelinePosition
    * Returns the position of the $sub_type for the $user_id
    * @param int $items_id is the id of the item (tickets_id, problems_id, ...)
    * @param string $sub_type is TicketFollowup, Document_Item, TicketTask, TicketValidation or Solution
    * @param int $users_id
    * @since 9.2
    */
   static function getTimelinePosition($items_id, $sub_type, $users_id) {
      return self::TIMELINE_NOTSET;
   }

}

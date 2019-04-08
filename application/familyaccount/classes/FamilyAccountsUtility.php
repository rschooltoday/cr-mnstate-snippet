<?php

class FamilyAccountsUtility
{
    const FAMILY_ACCOUNT_SESSION = "FAMILY_ACCOUNTS";

    const FAMILY_DIRECTORY_DEFAULT = "FAMILY_DIRECTORY_DEFAULT";
    const FAMILY_DIRECTORY_SHOW_DEFAULT = "FAMILY_DIRECTORY_SHOW_DEFAULT";
    const FAMILY_DIRECTORY_SHOW_CREATION_DATE = "FAMILY_DIRECTORY_SHOW_CREATION_D";
    const FAMILY_DIRECTORY_SHOW_AMOUNT_DUE = "FAMILY_DIRECTORY_SHOW_AMOUNT_DUE";
    const FAMILY_DIRECTORY_SHOW_ID_NO = "FAMILY_DIRECTORY_SHOW_ID_NO";
    const FAMILY_DIRECTORY_SHOW_LAST_REG = "FAMILY_DIRECTORY_SHOW_LAST_REG";
    const FAMILY_DIRECTORY_SHOW_PHONE = "FAMILY_DIRECTORY_SHOW_PHONE";
    const FAMILY_DIRECTORY_SHOW_ADDRESS = "FAMILY_DIRECTORY_SHOW_ADDRESS";
    const FAMILY_SEARCH_VIEW = "FAMILY_SEARCH_VIEW";
    const FAMILY_SEARCH_BY = "FAMILY_SEARCH_BY";
    const FAMILY_SEARCH_TEXT = "FAMILY_SEARCH_TEXT";
    const FAMILY_SEARCH_ALPHA = "FAMILY_SEARCH_ALPHA";
    const FAMILY_SEARCH_PROGRAM = "FAMILY_SEARCH_PROGRAM";
    const FAMILY_SEARCH_ADMIN = "FAMILY_SEARCH_ADMIN";
    const FAMILY_SEARCH_DATE_FROM = "FAMILY_SEARCH_DATE_FROM";
    const FAMILY_SEARCH_DATE_TO = "FAMILY_SEARCH_DATE_TO";

    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;

    function getGenders()
    {
        $genders = array(FamilyAccountsUtility::GENDER_MALE => "Male",
            FamilyAccountsUtility::GENDER_FEMALE => "Female",);

        return $genders;
    }

    function getStudents($truncate = false, $search = "", $letter = "")
    {
        $result = Students::getStudentsBySearch($truncate, $search, $letter);
        return $result;
    }

    function getPayerIds($truncate = false, $alphaRange, $search = "", $letter = "")
    {
        $query = new QueryCreator();

        $query->addFrom(FamilyAccount::TABLE_NAME . " as fa ");
        $query->addJoin("INNER JOIN " . FamilyAccountPayer::TABLE_NAME . " as fap ON fap.family_account_id = fa.id");
        $query->addJoin("INNER JOIN " . Payer::TABLE_NAME . " as p on p.id=fap.payer_id");
        $query->addSelect("p.id as id");

        $query->addWhere("fa.deleted = 0");
        $query->addWhere("p.deleted = 0");

        if ($search) {
            $query->addJoin("LEFT JOIN " . PayerContact::TABLE_NAME . " as pc ON p.id = pc.payer_id");
            $query->addWhere(" ( p.last_name like '%" . $search . "%' OR p.first_name like '%" . $search . "%'  OR pc.value LIKE '%" . $search . "%' ) ");
        }
        if ($letter) {
            $query->addWhere(" p.last_name like '" . $letter . "%' ");
        }

        if ($alphaRange) {
            list($from, $to) = explode("-", $alphaRange);
            $query->addWhere(" MID(p.last_name,1," . $truncate . ") >= '" . $from . "' ");
            $query->addWhere(" MID(p.last_name,1," . $truncate . ") <= '" . $to . "' ");
        }

        $query->addOrderBy("p.last_name ASC");
 
        $db = DBCon::instance();
        $result = $db->executeQuery($query->createSQL());
        return $result;
    }

    function getPayers($truncate = false, $search = "", $letter = "", $alphaRange = "")
    {
        $query = new QueryCreator();

        $query->addFrom(FamilyAccount::TABLE_NAME . " as fa ");
        $query->addJoin("INNER JOIN " . FamilyAccountPayer::TABLE_NAME . " as fap ON fap.family_account_id = fa.id");
        $query->addJoin("INNER JOIN " . Payer::TABLE_NAME . " as p on p.id=fap.payer_id");
        $query->addSelect("p.id as id");
        if ($truncate)
            $query->addSelect("MID(p.last_name,1," . $truncate . ") as name");
        $query->addWhere("fa.deleted = 0");
        $query->addWhere("p.deleted = 0");

        if ($search) {
            $query->addJoin("LEFT JOIN " . PayerContact::TABLE_NAME . " as pc ON p.id = pc.payer_id");
            $query->addWhere(" ( p.last_name like '%" . $search . "%' OR p.first_name like '%" . $search . "%' OR pc.value LIKE '%" . $search . "%') ");
        }
        if ($letter) {
            $query->addWhere(" p.last_name like '" . $letter . "%' ");
        }

        if ($alphaRange) {
            $rangeArr = explode("-", $alphaRange);
            if (count($rangeArr) > 1) {
                list($from, $to) = $rangeArr;
                if ($from && $to) {
                    $query->addWhere(" MID(p.last_name,1," . $truncate . ") >= '" . $from . "' ");
                    $query->addWhere(" MID(p.last_name,1," . $truncate . ") <= '" . $to . "' ");
                }
            }
        }

        $query->addOrderBy("p.last_name ASC");
 
        $db = DBCon::instance();
        $result = $db->executeQuery($query->createSQL());
        return $result;
    }

    //accounts listing
    public function getPayersInfo($payer_ids = null)
    {
        $contactTypesArray = ContactTypes::getContactTypesArray();

        $query = new QueryCreator();
        $query->addFrom(FamilyAccount::TABLE_NAME . " as fa ");
        $query->addJoin("INNER JOIN " . FamilyAccountPayer::TABLE_NAME . " as fap ON fap.family_account_id = fa.id");
        $query->addJoin("INNER JOIN " . Payer::TABLE_NAME . " as p on p.id=fap.payer_id");
        $query->addJoin("LEFT JOIN " . PayerAddress::TABLE_NAME . " as pa on p.id=pa.payer_id");
        $query->addJoin("LEFT JOIN " . PayerContact::TABLE_NAME . " as pci_mail ON p.id = pci_mail.payer_id AND pci_mail.contact_type_id = " . $contactTypesArray[ContactType::CODE_EMAIL]);
        $query->addJoin("LEFT JOIN " . PayerContact::TABLE_NAME . " as pci_phone ON p.id = pci_phone.payer_id AND pci_phone.contact_type_id = " . $contactTypesArray[ContactType::CODE_HOMEPHONE]);

        if ($payer_ids) {
            if (is_array($payer_ids))
                $payer_ids = implode(",", $payer_ids);
            $query->addWhere("p.id IN (" . $payer_ids . ")");
        }

        $query->addSelect(" fa.id as family_account_id");
        $query->addSelect(" p.last_name , p.first_name ");
        $query->addSelect(" pa.address, pa.address2 , pa.city ");
        $query->addSelect(" pci_mail.value as email ");
        $query->addSelect(" pci_phone.value as phone ");


        $query->addOrderBy("p.last_name ASC");
        $query->addGroupBy("family_account_id");
 
        $db = DBCon::instance();
        $payers = $db->executeQuery($query->createSQL());

        return $payers;
    }

    //accounts listing
    public function getStudentsInfo($student_ids = null)
    {
        $contactTypesArray = ContactTypes::getContactTypesArray();

        $query = new QueryCreator();
        $query->addFrom(FamilyAccount::TABLE_NAME . " as fa ");
        $query->addJoin("INNER JOIN " . FamilyAccountStudent::TABLE_NAME . " as fas ON fas.family_account_id = fa.id");
        $query->addJoin("INNER JOIN " . Student::TABLE_NAME . " as s on s.id=fas.student_id");
        $query->addJoin("LEFT JOIN " . StudentAddress::TABLE_NAME . " as sa on s.id=sa.student_id");
        $query->addJoin("LEFT JOIN " . StudentContact::TABLE_NAME . " as sci_phone ON s.id = sci_phone.student_id AND sci_phone.contact_type_id = " . $contactTypesArray[ContactType::CODE_HOMEPHONE]);
        $query->addJoin("LEFT JOIN " . AgeGroup::TABLE_NAME . " as ag ON ag.id = s.age_group_id ");
 
        if ($student_ids) {
            if (is_array($student_ids))
                $student_ids = implode(",", $student_ids);
            $query->addWhere("s.id IN (" . $student_ids . ")");
        }

        $query->addSelect(" fa.id as family_account_id");
        $query->addSelect(" s.last_name , s.first_name , s.id_no");
        $query->addSelect(" sa.address, sa.address2 ");
        $query->addSelect(" sci_phone.value as phone ");
        $query->addSelect(" ag.name as age_group ");
        $query->addSelect(" s.grade_level as grade ");


        $query->addOrderBy("s.last_name ASC");
        $query->addGroupBy("family_account_id");

        $db = DBCon::instance();
        $students = $db->executeQuery($query->createSQL());

        return $students;
    }


    public function getStudentInfo($studentId)
    {
        if ($studentId) {
            $query = new QueryCreator();

            $query->addSelect(" s.* , s.id as studentId ");
            $query->addSelect(" sd.* ");
            $query->addSelect(" sa.* ");
            $query->addSelect(" spp.first_name as pickup_person, spp.relation as pickup_person_relation");
            $query->addFrom(Student::TABLE_NAME . " as s ");
            $query->addJoin("LEFT JOIN " . StudentDetail::TABLE_NAME . " as sd ON sd.student_id = s.id");
            $query->addJoin("LEFT JOIN " . StudentAddress::TABLE_NAME . " as sa ON sa.student_id = s.id AND sa.primary_address = 1 ");
            $query->addJoin("LEFT JOIN " . StudentPickupPerson::TABLE_NAME . " as spp ON spp.student_id = s.id  ");

            $query->addWhere("s.id = " . $studentId);

            $db = DBCon::instance();
            $student = $db->executeQuery($query->createSQL());
            $studentArr = $student[0];

            $studentContacts = StudentContacts::getContacts($studentId, array(ContactType::CODE_EMAIL,
                ContactType::CODE_DAYPHONE,
                ContactType::CODE_HOMEPHONE,
                ContactType::CODE_OTHERPHONE));
            if ($studentContacts) {
                $otherPhonesCount = 0;
                foreach ($studentContacts as $studentContact) {
                    if ($studentContact['code'] == ContactType::CODE_EMAIL) {
                        $studentArr['email'] = $studentContact['value'];
                    } elseif ($studentContact['code'] == ContactType::CODE_DAYPHONE) {
                        $studentArr['daytime_phone'] = $studentContact['value'];
                    } elseif ($studentContact['code'] == ContactType::CODE_HOMEPHONE) {
                        $studentArr['home_phone'] = $studentContact['value'];
                    } elseif ($studentContact['code'] == ContactType::CODE_OTHERPHONE) {
                        $otherPhonesCount++;
                        $studentArr['phone_' . $otherPhonesCount] = $studentContact['value'];
                        $studentArr['phone_' . $otherPhonesCount . '_desc'] = $studentContact['purpose'];
                    }
                }
            }

            $studentArr['student_profiles'] = StudentCustomerProfiles::getByStudentID($studentId);
            $studentArr['id'] = $studentArr['studentId'];

            return $studentArr;
        }
    }

    public function getPayerInfo($payerId)
    {
        if ($payerId) {
            $query = new QueryCreator();

            $query->addSelect(" p.* , p.id as payerId ");
            $query->addSelect(" fap.family_account_id ");
            $query->addSelect(" u.* ");
            $query->addSelect(" pa.* ");
            $query->addSelect(" pcc.id AS card_id,
                                  pcc.first_name AS card_first_name,
                                  pcc.last_name AS card_last_name,
                                  pcc.card_number AS card_number");
            $query->addSelect(" pcc.expiration AS card_expiration_date,
                                    pcc.back_number AS card_back_digits,
                                    pcc.card_type_id ");
            $query->addSelect(" fa.account_name, fa.is_organization ");
            $query->addFrom(Payer::TABLE_NAME . " as p ");
            $query->addJoin("INNER JOIN " . User::TABLE_NAME . " as u ON u.id = p.user_id ");
            $query->addJoin("LEFT JOIN " . PayerAddress::TABLE_NAME . " as pa ON pa.payer_id = p.id AND pa.primary_address = 1 ");
            $query->addJoin("LEFT JOIN " . PayerCreditCard::TABLE_NAME . " as pcc ON pcc.payer_id = p.id  ");
            $query->addJoin("LEFT JOIN " . FamilyAccountPayer::TABLE_NAME . " as fap ON p.id = fap.payer_id ");
            $query->addJoin("LEFT JOIN " . FamilyAccount::TABLE_NAME . " as fa ON fa.id = fap.family_account_id ");

            $query->addWhere("p.id = " . $payerId);

            $db = DBCon::instance();
            $payer = $db->executeQuery($query->createSQL());
            $payerArr = $payer[0];

            $payerContacts = PayerContacts::getContacts($payerId,
                array(ContactType::CODE_EMAIL,
                    ContactType::CODE_HOMEPHONE,
                    ContactType::CODE_OFFICEPHONE,
                    ContactType::CODE_OFFICEPHONE_EXT,
                    ContactType::CODE_FAX));
            if ($payerContacts) {
                foreach ($payerContacts as $payerContact) {
                    if ($payerContact['code'] == ContactType::CODE_EMAIL) {
                        $payerArr['contact_email'] = $payerContact['value'];
                    } elseif ($payerContact['code'] == ContactType::CODE_HOMEPHONE) {
                        $payerArr['contact_phone'] = $payerContact['value'];
                    } elseif ($payerContact['code'] == ContactType::CODE_OFFICEPHONE) {
                        $payerArr['office_phone'] = $payerContact['value'];
                    } elseif ($payerContact['code'] == ContactType::CODE_OFFICEPHONE_EXT) {
                        $payerArr['extension'] = $payerContact['value'];
                    } elseif ($payerContact['code'] == ContactType::CODE_FAX) {
                        $payerArr['fax'] = $payerContact['value'];
                    }
                }
            }

            $payerArr['id'] = $payerArr['payerId'];
 
            return $payerArr;
        }
    }

    public function getGuardianInfo($guardianId)
    {
        if ($guardianId) {
            $query = new QueryCreator();

            $query->addSelect(" g.* , g.id as guardianId ");
            $query->addSelect(" ga.* ");
            $query->addSelect(" fag.primary_contact_person ");
            $query->addFrom(Guardian::TABLE_NAME . " as g ");
            $query->addJoin(" INNER join " . FamilyAccountGuardian::TABLE_NAME . " fag ON g.id = fag.guardian_id ");
            $query->addJoin("LEFT JOIN " . GuardianAddress::TABLE_NAME . " as ga ON ga.guardian_id = g.id AND ga.primary_address = 1 ");

            $query->addWhere("g.id = " . $guardianId);

            $db = DBCon::instance();
            $guardians = $db->executeQuery($query->createSQL());
            $guardianArr = $guardians[0];

            $guardianContacts = GuardianContacts::getContacts($guardianId, array(ContactType::CODE_EMAIL,
                ContactType::CODE_DAYPHONE,
                ContactType::CODE_DAYPHONE_EXT,
                ContactType::CODE_NIGHTPHONE,
                ContactType::CODE_NIGHTPHONE_EXT,
                ContactType::CODE_MOBILE));
            if ($guardianContacts) {
                foreach ($guardianContacts as $guardianContact) {
                    if ($guardianContact['code'] == ContactType::CODE_EMAIL) {
                        $guardianArr['contact_email'] = $guardianContact['value'];
                    } elseif ($guardianContact['code'] == ContactType::CODE_DAYPHONE) {
                        $guardianArr['day_phone'] = $guardianContact['value'];
                    } elseif ($guardianContact['code'] == ContactType::CODE_DAYPHONE_EXT) {
                        $guardianArr['day_phone_ext'] = $guardianContact['value'];
                    } elseif ($guardianContact['code'] == ContactType::CODE_NIGHTPHONE) {
                        $guardianArr['night_phone'] = $guardianContact['value'];
                    } elseif ($guardianContact['code'] == ContactType::CODE_NIGHTPHONE_EXT) {
                        $guardianArr['night_phone_ext'] = $guardianContact['value'];
                    } elseif ($guardianContact['code'] == ContactType::CODE_MOBILE) {
                        $guardianArr['mobile'] = $guardianContact['value'];
                    }
                }
            }

            $guardianArr['id'] = $guardianArr['guardianId'];
            return $guardianArr;
        }
    }

    public function saveCCSahre($payerShareCC)
    {
        if ($payerShareCC) {
            $db = DBCon::instance();
            foreach ($payerShareCC as $key => $sharecc) {
                $queryupdate = "UPDATE payer_cards SET share_cc = $sharecc WHERE  payer_id = $key ";
                $db->executeCommand($queryupdate);
            }
        }
    }

    public function savePerCCSahre($payerShareCC)
    {
        if ($payerShareCC) {
            foreach ($payerShareCC as $key => $sharecc) {
                $payerCreditCard = new PayerCreditCard();
                $payerCreditCard->loadWhere("Id=$key");
                $payerCreditCard->share_cc = $sharecc;
                $payerCreditCard->save();
            }
        }
    }

    public function savePayerInfo($payer)
    {
        $oPayer = new Payer();
        if ($payer->id) {
            $oPayer->loadById($payer->id);
            $payer->user_id = $oPayer->user_id;
        } else {
            $payer->id = null;
        }

        $oUser = new User();
        if (isset($payer->user_id) && $payer->user_id) {
            $oUser->loadById($payer->user_id);
        }
        $oUser->username = $payer->username;
        if ($payer->password) {
            $oUser->password = Utility_AuthUtility::hashPassword($payer->password);
        }
        $defaultAppAccess = FamilyAccountsUtility::getDefaultPayerAppAccess();
        $oUser->app_access = $payer->app_access ? $payer->app_access : $defaultAppAccess;
        $oUser->user_type_id = UserUtility::USER_TYPE_PAYERS;
        $oUser->created_by_id = Utility_AuthUtility::getCurrentUserId();
        $oUser->deleted = 0;
        $oUser->save();

        $oPayer->setFromArray((array)$payer);
        $oPayer->created_by_id = Utility_AuthUtility::getCurrentUserId();
        $oPayer->user_id = $oUser->id;

        $oPayer->save();

        if (isset($payer->payment_type) && $payer->payment_type = "credit_card") {
            $payerCreditCard = new PayerCreditCard();
            $payerCreditCard->loadByPayerId($oPayer->id);
            $payerCreditCard->first_name = $payer->card_first_name;
            $payerCreditCard->last_name = $payer->card_last_name;
            $payerCreditCard->number = $payer->card_number;
            $payerCreditCard->card_type_id = $payer->card_type_id;
            $payerCreditCard->payer_id = $oPayer->id;
            $payerCreditCard->expiration_date = TimeUtility::convertToSQLDateFormat(date("m/d/Y", mktime(0, 0, 0, $payer->card_expiration_month, 1, $payer->card_expiration_year)));
            $payerCreditCard->save();
        }

        $familyAccountPayer = new FamilyAccountPayer();
        $familyAccountPayer->loadByFamilyAccountIdPayerId($payer->family_account_id, $oPayer->id);
        $familyAccountPayer->family_account_id = $payer->family_account_id;
        $familyAccountPayer->payer_id = $oPayer->id;
        $familyAccountPayer->save();

        $addressTypesArray = AddressTypes::getAddressTypesArray();

        $payerAddress = new PayerAddress();
        $payerAddress->loadByPayerId($oPayer->id);
        $payerAddress->setFromArray((array)$payer);
        $payerAddress->address_type_id = $addressTypesArray[AddressType::CODE_HOME];
        $payerAddress->primary_address = 1;
        $payerAddress->payer_id = $oPayer->id;
        $payerAddress->save();


        $contactTypesArray = ContactTypes::getContactTypesArray();

        $payerContact = new PayerContact();
        if (isset($payer->contact_email)) {
            $payerContact->clear();
            $payerContact->loadByPayerIdContactType($oPayer->id, ContactType::CODE_EMAIL);
            if (!$payer->contact_email && $payerContact->id) {
                $payerContact->delete(true);
            } elseif ($payer->contact_email) {
                $payerContact->contact_type_id = $contactTypesArray[ContactType::CODE_EMAIL];
                $payerContact->value = $payer->contact_email;
                $payerContact->primary_contact = 0;
                $payerContact->payer_id = $oPayer->id;
                $payerContact->save();
            }
        }
        if (isset($payer->contact_phone)) {
            $payerContact->clear();
            $payerContact->loadByPayerIdContactType($oPayer->id, ContactType::CODE_HOMEPHONE);
            if (!$payer->contact_phone && $payerContact->id) {
                $payerContact->delete(true);
            } elseif ($payer->contact_phone) {
                $payerContact->contact_type_id = $contactTypesArray[ContactType::CODE_HOMEPHONE];
                $payerContact->value = $payer->contact_phone;
                $payerContact->primary_contact = 0;
                $payerContact->payer_id = $oPayer->id;
                $payerContact->save();
            }
        }
        if (isset($payer->office_phone)) {
            $payerContact->clear();
            $payerContact->loadByPayerIdContactType($oPayer->id, ContactType::CODE_OFFICEPHONE);
            if (!$payer->office_phone && $payerContact->id) {
                $payerContact->delete(true);
            } elseif ($payer->office_phone) {
                $payerContact->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE];
                $payerContact->value = $payer->office_phone;
                $payerContact->primary_contact = 0;
                $payerContact->payer_id = $oPayer->id;
                $payerContact->save();
            }
        }
        if (isset($payer->extension)) {
            $payerContact->clear();
            $payerContact->loadByPayerIdContactType($oPayer->id, ContactType::CODE_OFFICEPHONE_EXT);
            $payerContact->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE_EXT];
            if (!$payer->extension && $payerContact->id) {
                $payerContact->delete(true);
            } elseif ($payer->extension) {
                $payerContact->value = $payer->extension;
                $payerContact->primary_contact = 0;
                $payerContact->payer_id = $oPayer->id;
                $payerContact->save();
            }
        }
        if (isset($payer->fax)) {
            $payerContact->clear();
            $payerContact->loadByPayerIdContactType($oPayer->id, ContactType::CODE_FAX);
            $payerContact->contact_type_id = $contactTypesArray[ContactType::CODE_FAX];
            if (!$payer->fax && $payerContact->id) {
                $payerContact->delete(true);
            } elseif ($payer->fax) {
                $payerContact->value = $payer->fax;
                $payerContact->primary_contact = 0;
                $payerContact->payer_id = $oPayer->id;
                $payerContact->save();
            }
        }

    }

    function getDefaultPayerAppAccess()
    {
        $defaultAppAccess = Utility_DWCAppCodes::APP_CR + Utility_DWCAppCodes::APP_RSS;
        return $defaultAppAccess;
    }

    public function saveStudentInfo($student)
    {
        $oStudent = new Student();
        if ($student->id) {
            $oStudent->loadById($student->id);
        } else {
            $student->id = null;
        }
        $oStudent->setFromArray((array)$student);
        $oStudent->created_by_id = Utility_AuthUtility::getCurrentUserId();
        $oStudent->save();


        $familyAccountStudent = new FamilyAccountStudent();
        $familyAccountStudent->loadByFamilyAccountIdStudentId($student->family_account_id, $oStudent->id);
        $familyAccountStudent->family_account_id = $student->family_account_id;
        $familyAccountStudent->student_id = $oStudent->id;
        $familyAccountStudent->save();


        $studentCustomerProfiles = new StudentCustomerProfiles();
        $studentCustomerProfiles->deleteByStudentID($oStudent->id);

        if (isset($student->student_profiles) && is_array($student->student_profiles)) {
            $studentCustomerProfile = new StudentCustomerProfile();
            foreach ($student->student_profiles as $profile) {
                $studentCustomerProfile->clear();
                $studentCustomerProfile->loadByStudentIdAndProfileId($oStudent->id, $profile['customer_profile_id']);
                $studentCustomerProfile->customer_profile_id = $profile['customer_profile_id'];
                $studentCustomerProfile->student_id = $oStudent->id;
                $studentCustomerProfile->save();
            }
        }

        $studentOtherDetail = new StudentDetail();
        $studentOtherDetail->loadByStudentId($oStudent->id);
        $studentOtherDetail->setFromArray((array)$student);
        if (!$studentOtherDetail->shirt_size_id) {
            $studentOtherDetail->shirt_size_id = 0;
        }
        $studentOtherDetail->student_id = $oStudent->id;
        if ($studentOtherDetail->physical_date) {
            $studentOtherDetail->physical_date = TimeUtility::convertToSQLDateFormat($studentOtherDetail->physical_date);
        }
        $studentOtherDetail->birthdate = TimeUtility::convertToSQLDateFormat($oStudent->birthdate);
        $studentOtherDetail->classroom_teacher = $student->classroom_teacher;
        $studentOtherDetail->save();

        $contactTypesArray = ContactTypes::getContactTypesArray();

        $studentContact = new StudentContact();
        if (isset($student->email)) {
            $studentContact->clear();
            $studentContact->loadByStudentIdContactType($oStudent->id, ContactType::CODE_EMAIL);
            $studentContact->contact_type_id = $contactTypesArray[ContactType::CODE_EMAIL];
            $studentContact->value = $student->email;
            $studentContact->primary_contact = 0;
            $studentContact->student_id = $oStudent->id;
            $studentContact->save();
        }
        if (isset($student->daytime_phone)) {
            $studentContact->clear();
            $studentContact->loadByStudentIdContactType($oStudent->id, ContactType::CODE_DAYPHONE);
            $studentContact->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE];
            $studentContact->value = $student->daytime_phone;
            $studentContact->primary_contact = 0;
            $studentContact->student_id = $oStudent->id;
            $studentContact->save();
        }
        if (isset($student->home_phone)) {
            $studentContact->clear();
            $studentContact->loadByStudentIdContactType($oStudent->id, ContactType::CODE_HOMEPHONE);
            $studentContact->contact_type_id = $contactTypesArray[ContactType::CODE_HOMEPHONE];
            $studentContact->value = $student->home_phone;
            $studentContact->primary_contact = 0;
            $studentContact->student_id = $oStudent->id;
            $studentContact->save();
        }

        //delete-insert other_phone records
        for ($i = 1; $i <= 4; $i++) {
            $phonevar = "phone_$i";
            $phonevardesc = "phone_$i" . "_desc";
            if (isset($student->$phonevar) && $student->$phonevar && $phonevardesc) {
                $studentContact->clear();
                $studentContact->contact_type_id = $contactTypesArray[ContactType::CODE_OTHERPHONE];
                $studentContact->value = $student->$phonevar;
                $studentContact->primary_contact = 0;
                $studentContact->student_id = $oStudent->id;
                $studentContact->save();
            }
        }

        $addressTypesArray = AddressTypes::getAddressTypesArray();

        $studentAddress = new StudentAddress();
        $studentAddress->loadByStudentId($oStudent->id);
        $studentAddress->setFromArray((array)$student);
        $studentAddress->address_type_id = $addressTypesArray[AddressType::CODE_HOME];
        $studentAddress->primary_address = 1;
        $studentAddress->student_id = $oStudent->id;
        $studentAddress->save();

        if (isset($student->pickup_person)) {
            $studentPickupPerson = new StudentPickupPerson();
            $studentPickupPerson->loadByStudentId($oStudent->id);
            $studentPickupPerson->first_name = $student->pickup_person;
            $studentPickupPerson->last_name = "";
            $studentPickupPerson->relation = $student->pickup_person_relation;
            $studentPickupPerson->student_id = $oStudent->id;
            $studentPickupPerson->save();
        }


    }

    public function saveGuardianInfo($guardian)
    {
        $oGuardian = new Guardian();
        if ($guardian->id) {
            $oGuardian->loadById($guardian->id);
        } else {
            $guardian->id = null;
        }

        $oGuardian->setFromArray((array)$guardian);
        $oGuardian->created_by_id = Utility_AuthUtility::getCurrentUserId();
        $oGuardian->save();


        $familyAccountGuardian = new FamilyAccountGuardian();
        $familyAccountGuardian->loadByFamilyAccountIdGuardianId($guardian->family_account_id, $oGuardian->id);
        $familyAccountGuardian->family_account_id = $guardian->family_account_id;
        $familyAccountGuardian->guardian_id = $oGuardian->id;
        if (isset($guardian->primary_contact_person)) {
            $familyAccountGuardian->primary_contact_person = $guardian->primary_contact_person;
        } else {
            $familyAccountGuardian->primary_contact_person = 0;
        }
        $familyAccountGuardian->save();

        $addressTypesArray = AddressTypes::getAddressTypesArray();

        $guardianAddress = new GuardianAddress();
        $guardianAddress->loadByGuardianId($oGuardian->id);
        $guardianAddress->setFromArray((array)$guardian);
        $guardianAddress->address_type_id = $addressTypesArray[AddressType::CODE_HOME];
        $guardianAddress->primary_address = 1;
        $guardianAddress->guardian_id = $oGuardian->id;
        $guardianAddress->save();

        $contactTypesArray = ContactTypes::getContactTypesArray();

        $guardianrContact = new GuardianContact();
        if (isset($guardian->contact_email)) {
            $guardianrContact->clear();
            $guardianrContact->loadByGuardianIdContactType($oGuardian->id, ContactType::CODE_EMAIL);
            if (!$guardian->contact_email && $guardianrContact->id) {
                $guardianrContact->delete(true);
            } elseif ($guardian->contact_email) {
                $guardianrContact->contact_type_id = $contactTypesArray[ContactType::CODE_EMAIL];
                $guardianrContact->value = $guardian->contact_email;
                $guardianrContact->primary_contact = 0;
                $guardianrContact->guardian_id = $oGuardian->id;
                $guardianrContact->save();
            }
        }
        if (isset($guardian->day_phone)) {
            $guardianrContact->clear();
            $guardianrContact->loadByGuardianIdContactType($oGuardian->id, ContactType::CODE_DAYPHONE);
            if (!$guardian->day_phone && $guardianrContact->id) {
                $guardianrContact->delete(true);
            } elseif ($guardian->day_phone) {
                $guardianrContact->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE];
                $guardianrContact->value = $guardian->day_phone;
                $guardianrContact->primary_contact = 0;
                $guardianrContact->guardian_id = $oGuardian->id;
                $guardianrContact->save();
            }
        }
        if (isset($guardian->day_phone_ext)) {
            $guardianrContact->clear();
            $guardianrContact->loadByGuardianIdContactType($oGuardian->id, ContactType::CODE_DAYPHONE_EXT);
            $guardianrContact->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE_EXT];
            if (!$guardian->day_phone_ext && $guardianrContact->id) {
                $guardianrContact->delete(true);
            } elseif ($guardian->day_phone_ext) {
                $guardianrContact->value = $guardian->day_phone_ext;
                $guardianrContact->primary_contact = 0;
                $guardianrContact->guardian_id = $oGuardian->id;
                $guardianrContact->save();
            }
        }
        if (isset($guardian->night_phone)) {
            $guardianrContact->clear();
            $guardianrContact->loadByGuardianIdContactType($oGuardian->id, ContactType::CODE_NIGHTPHONE);
            if (!$guardian->night_phone && $guardianrContact->id) {
                $guardianrContact->delete(true);
            } elseif ($guardian->night_phone) {
                $guardianrContact->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE];
                $guardianrContact->value = $guardian->night_phone;
                $guardianrContact->primary_contact = 0;
                $guardianrContact->guardian_id = $oGuardian->id;
                $guardianrContact->save();
            }
        }
        if (isset($guardian->night_phone_ext)) {
            $guardianrContact->clear();
            $guardianrContact->loadByGuardianIdContactType($oGuardian->id, ContactType::CODE_NIGHTPHONE_EXT);
            $guardianrContact->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE_EXT];
            if (!$guardian->night_phone_ext && $guardianrContact->id) {
                $guardianrContact->delete(true);
            } elseif ($guardian->night_phone_ext) {
                $guardianrContact->value = $guardian->night_phone_ext;
                $guardianrContact->primary_contact = 0;
                $guardianrContact->guardian_id = $oGuardian->id;
                $guardianrContact->save();
            }
        }
        if (isset($guardian->mobile)) {
            $guardianrContact->clear();
            $guardianrContact->loadByGuardianIdContactType($oGuardian->id, ContactType::CODE_MOBILE);
            $guardianrContact->contact_type_id = $contactTypesArray[ContactType::CODE_MOBILE];
            if (!$guardian->mobile && $guardianrContact->id) {
                $guardianrContact->delete(true);
            } elseif ($guardian->mobile) {
                $guardianrContact->value = $guardian->mobile;
                $guardianrContact->primary_contact = 0;
                $guardianrContact->guardian_id = $oGuardian->id;
                $guardianrContact->save();
            }
        }
    }


    function saveFamilyAccountInfo($familyAccountInfo, $payerInfo, $isMainPayer = true, $public = 0)
    {
        $currentUserId = Utility_AuthUtility::getCurrentUserId();
        if ($isMainPayer) {
            $familyAccount = new FamilyAccount();
            $accountId = (int)$familyAccountInfo['id'];
            if ($accountId) {
                $familyAccount->load($accountId);
            }

            if (isset($familyAccountInfo['account_name'])) {
                $familyAccount->account_name = $familyAccountInfo['account_name'];
            } else {
                $familyAccount->account_name = '';
            }

            if (isset($familyAccountInfo['family_size'])) {
                $familyAccount->family_size = (int)$familyAccountInfo['family_size'];
            } else {
                $familyAccount->family_size = 0;
            }

            if (isset($familyAccountInfo['family_income'])) {
                $familyAccount->family_income = (float)$familyAccountInfo['family_income'];
            } else {
                $familyAccount->family_income = 0;
            }

            $familyAccount->updated_by_id = ($currentUserId) ? $currentUserId : NULL;
            $familyAccount->created_by_id = ($currentUserId) ? $currentUserId : NULL;
            $familyAccount->created_from = $public ? "public" : "admin";
            $familyAccount->save();
        } else {
            $userId = (int)$payerInfo['user_id'];
            $familyAccount = new FamilyAccount();
            $accountId = (int)$familyAccountInfo['id'];
            if ($accountId) {
                $familyAccount->load($accountId);
            }
            if ($userId && $familyAccount->main_payer_id && $userId = $familyAccount->main_payer_id) {
                if (isset($familyAccountInfo['account_name'])) {
                    $familyAccount->account_name = $familyAccountInfo['account_name'];
                } else {
                    $familyAccount->account_name = '';
                }
            }
        }

        $user = new User();
        $user->load($payerInfo['user_id']);
        if ($user->id) {
            if ($payerInfo['enabled'] == 0) {
                $user->active = 0;
                $user->save();
            } else {
                $user->active = 1;
                $user->save();
            }
        }
        $payerData = new Zend_Session_Namespace('PAYER_DATA');
        $payerData->id = FamilyAccountsUtility::savePayerForFamilyAccount($familyAccount->id, $payerInfo, $isMainPayer);

        return $familyAccount->id;
    }

    function savePayerForFamilyAccount($familyAccountId, $payerInfo, $isMainPayer = false, $override)
    {
        $currentUserId = Utility_AuthUtility::getCurrentUserId();
        $userChanged = false;

        //set status for batchupdate
        $ifnotbatch = ($payerInfo['from_batch']) ? FALSE : TRUE;

        $user = new User();
        $userId = (int)$payerInfo['user_id'];
        if ($userId) {
            $user->load($userId);
            if ($payerInfo['username'] == '') {
                $user->username = 'auto_' . $userId;
                $user->password = Utility_AuthUtility::hashPassword($userId);
                $userChanged = true;
            }
            if ($payerInfo['username'] != '' && $payerInfo['username'] != $user->username) {
                $user->username = $payerInfo['username'];
                $userChanged = true;
            }
            if ($payerInfo['password']) {
                $user->password = Utility_AuthUtility::hashPassword($payerInfo['password']);
                $userChanged = true;
            }
        } else {
            if ($payerInfo['username'] != '')
                $user->username = $payerInfo['username'];
            else
                $user->username = $payerInfo['autousername'];
            if ($payerInfo['password'])
                $user->password = Utility_AuthUtility::hashPassword($payerInfo['password']);
            else
                $user->password = Utility_AuthUtility::hashPassword($payerInfo['autopassword']);

            $user->sec_key = Utility_AuthUtility::hashPassword($user->password);
            if ($user->username != NULL)
                $userChanged = true;
        }
        $payerInfo['sec_key'] = $user->sec_key;

        if ($userChanged) {
            $defaultAppAccess = FamilyAccountsUtility::getDefaultPayerAppAccess();
            $user->app_access = $user->app_access ? $user->app_access : $defaultAppAccess;

            $user->user_type_id = UserUtility::USER_TYPE_PAYERS;
            $user->created_by_id = ($currentUserId) ? $currentUserId : NULL;
            $user->save();
            $payerInfo['user_id'] = $user->id;

            //save permission for current user
            $params = array("fields" => array("id"), "parentSetting" => "CR_PUBLIC");
            $permissions = ApplicationSettings::getApplicationSettings($params);
            foreach ($permissions as $permission)
                UserSettingPermission::saveRecord(true, $user->id, $permission["id"]);
        }

        $temp_fa = new FamilyAccount();
        $temp_fa->load($familyAccountId);
        if ($temp_fa->created_by_id == NULL) {
            $temp_fa->created_by_id = $user->id;
            $temp_fa->save();
        }

        $payer = new Payer();
        $payerId = (int)$payerInfo['id'];
        $updatestudent = false;
        if ($payerId) {
            $payer->load($payerId);
            $payerstudent = Payers::payerAsStudent($payerId);

            if ($payerstudent[0]) {
                $updatestudent = true;
                $studentid = $payerstudent[0];
            }
        } else {
            $payer->created_on = date('Y-m-d');
        }

        if ($override) {
            $payer->first_name = $payerInfo['first_name'];
            if (isset($payerInfo['middle_name']) && $payerInfo['middle_name'] != "") {
                $payer->middle_name = $payerInfo['middle_name'];
            }

            if ($payerInfo['middle_name'] == "") {
                $payer->middle_name = "";
            }
            $payer->last_name = $payerInfo['last_name'];
            $payer->gender = (int)$payerInfo['gender'];
            $payer->age_group_id = (int)$payerInfo['age_group_id'];
            if (isset($payerInfo['disability'])) {
                $payer->disability = $payerInfo['disability'];
            } else {
                $payer->disability = 0;
            }

            if (isset($payerInfo['email_preference'])) {
                $payer->email_preference = 1;
            } else {
                $payer->email_preference = 0;
            }

            if (isset($payerInfo['comments'])) {
                $payer->comments = $payerInfo['comments'];
            }

            if (isset($payerInfo['price_tier_id'])) {
                if ($payerInfo['price_tier_id'] == NULL || $payerInfo['price_tier_id'] == '') {
                    $payer->price_tier_id = NULL;
                } else {
                    $payer->price_tier_id = (int)$payerInfo['price_tier_id'];
                }
            }

        } else {
            if (isset($payerInfo['first_name']) && $payerInfo['first_name']) {
                $payer->first_name = $payerInfo['first_name'];
            }

            if (isset($payerInfo['middle_name']) && $payerInfo['middle_name'] != "") {
                $payer->middle_name = $payerInfo['middle_name'];
            }

            if ($payerInfo['middle_name'] == "" && $ifnotbatch == 1) { //For Bug 67153 comment #45
                $payer->middle_name = "";
            }

            if (isset($payerInfo['last_name']) && $payerInfo['last_name']) {
                $payer->last_name = $payerInfo['last_name'];
            }

            if (isset($payerInfo['gender']) && $payerInfo['gender']) {
                $payer->gender = $payerInfo['gender'];
            }

            if (isset($payerInfo['age_group_id']) && $payerInfo['age_group_id']) {
                $payer->age_group_id = $payerInfo['age_group_id'];
            }

            if (isset($payerInfo['disability'])) {
                $payer->disability = $payerInfo['disability'];
            } else {
                $payer->disability = 0;
            }

           if (isset($payerInfo['email_preference'])){
            	$payer->email_preference = 1;
            }else{
                $payer->email_preference = 0;
            }

            if (isset($payerInfo['comments'])){
                $payer->comments = $payerInfo['comments'];
            }

            if (isset($payerInfo['price_tier_id'])) {
                if ($payerInfo['price_tier_id'] == NULL || $payerInfo['price_tier_id'] == '') {
                    $payer->price_tier_id = NULL;
                } else {
                    $payer->price_tier_id = (int)$payerInfo['price_tier_id'];
                }
            }
        }

        if ($user->id) {
            $payer->user_id = (int)$user->id;
        }

        // in batch update, doesn't update the payer disability
        if ($ifnotbatch) {
            $payer->enabled = $payerInfo['enabled'];
            // if payer is disabled, disable the user so they cannot login
            $user = new User();
            $user->load($payer->user_id);
            $user->active = $payer->enabled;
            $user->save();
        }
        $payer->created_by_id = ($currentUserId) ? $currentUserId : NULL;
        $payer->save();

        if ($payerInfo['from_batch'] == 1) {
            $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
            if (file_exists($log))
                $handler = fopen($log, 'a') or die("can't open file {$log} append");
            else
                $handler = fopen($log, 'w') or die("can't open file {$log} write");
            fwrite($handler, "-- BATCH UPDATE \n");
            fwrite($handler, "BATCH UPDATE date: '" . date("F j, Y, g:i a") . "' \n");
            fwrite($handler, "BATCH UPDATE user_id: '" . $currentUserId . "' \n");
            fwrite($handler, "BATCH UPDATE family_account_id: '" . $familyAccountId . "' \n");
            fwrite($handler, "BATCH UPDATE payer_id: '" . $payerInfo['id'] . "' \n");
            fwrite($handler, "BATCH UPDATE first_name: '" . $payerInfo['first_name'] . "' \n");
            fwrite($handler, "BATCH UPDATE last_name: '" . $payerInfo['last_name'] . "' \n");
        }

        $familyPayer = new FamilyAccountPayer();
        $familyPayer->loadWhere("family_account_id = " . $familyAccountId . " AND payer_id = " . $payer->id);
        $familyPayer->family_account_id = $familyAccountId;
        $familyPayer->payer_id = $payer->id;

        if ($ifnotbatch) {
            if (isset($payerInfo['share_payment'])) {
                $familyPayer->share_payment_history = (int)$payerInfo['share_payment'];
            } else {
                $familyPayer->share_payment_history = 0;
            }

            if (isset($payerInfo['share_money'])) {
                $familyPayer->share_coa = (int)$payerInfo['share_money'];
            } else {
                $familyPayer->share_coa = 0;
            }
        }

        $familyPayer->save();

        if ($isMainPayer) {
            $familyAccount = new FamilyAccount();
            $familyAccount->load($familyAccountId);
            //set payer as main payer
            $familyAccount->main_payer_id = $payer->id;
            $familyAccount->save();
        }


        $payerAddress = new PayerAddress();

        if ($override) {
            $payerAddressId = (int)$payerInfo['address_id'];
            if ($payerAddressId) {
                $payerAddress->load($payerAddressId);
            } else {
                $payerAddress->loadWhere("payer_id = " . $payer->id . " AND primary_address = " . (int)$payerInfo['main_address']);
            }

            $payerAddress->address_type_id = $payerInfo['address_type_id'];
            $payerAddress->primary_address = $payerInfo['main_address'];
            $payerAddress->address = $payerInfo['address'];
            if (isset($payerInfo['address2']) && $payerInfo['address2'] != "") {
                $payerAddress->address2 = $payerInfo['address2'];
            }

            if ($payerInfo['address2'] == "") {
                $payerAddress->address2 = "";
            }
            $payerAddress->city = $payerInfo['city'];
            $payerAddress->state = $payerInfo['state'];
            $payerAddress->zip = $payerInfo['zip'];
            $payerAddress->foreign_zip = $payerInfo['foreign_zip'];
            $payerAddress->country = $payerInfo['country'];
        } else {
            $payerAddress->loadWhere("payer_id = " . $payer->id . " AND primary_address = " . (int)$payerInfo['main_address']);

            if (isset($payerInfo['address_type_id']) && $payerInfo['address_type_id']) {
                $payerAddress->address_type_id = $payerInfo['address_type_id'];
            }

            if (isset($payerInfo['main_address']) && $payerInfo['main_address']) {
                $payerAddress->primary_address = $payerInfo['main_address'];
            }

            if (isset($payerInfo['address']) && $payerInfo['address']) {
                $payerAddress->address = $payerInfo['address'];
            }

            if (isset($payerInfo['address2']) && $payerInfo['address2'] != "") {
                $payerAddress->address2 = $payerInfo['address2'];
            }

            if ($payerInfo['address2'] == "" && $ifnotbatch == 1) {
                $payerAddress->address2 = "";
            }

            if (isset($payerInfo['city']) && $payerInfo['city']) {
                $payerAddress->city = $payerInfo['city'];
            }

            if (isset($payerInfo['state']) && $payerInfo['state']) {
                $payerAddress->state = $payerInfo['state'];
            }

            if (isset($payerInfo['zip']) && $payerInfo['zip']) {
                $payerAddress->zip = $payerInfo['zip'];
            }

            if (isset($payerInfo['foreign_zip']) && $payerInfo['foreign_zip']) {
                $payerAddress->foreign_zip = $payerInfo['foreign_zip'];
            }

            if (isset($payerInfo['country']) && $payerInfo['country']) {
                $payerAddress->country = $payerInfo['country'];
            }
        }

        $payerAddress->payer_id = $payer->id;
        $payerAddress->save();

        if ($payerInfo['from_batch'] == 1) {
            $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
            if (file_exists($log))
                $handler = fopen($log, 'a') or die("can't open file {$log} append");
            else
                $handler = fopen($log, 'w') or die("can't open file {$log} write");
            fwrite($handler, "BATCH UPDATE address: '" . $payerInfo['address'] . "' \n");
            fwrite($handler, "BATCH UPDATE address2: '" . $payerInfo['address2'] . "' \n");
            fwrite($handler, "BATCH UPDATE city: '" . $payerInfo['city'] . "' \n");
            fwrite($handler, "BATCH UPDATE state: '" . $payerInfo['state'] . "' \n");
            fwrite($handler, "BATCH UPDATE zip: '" . $payerInfo['zip'] . "' \n");
            fwrite($handler, "BATCH UPDATE foreign_zip: '" . $payerInfo['foreign_zip'] . "' \n");
            fwrite($handler, "BATCH UPDATE country: '" . $payerInfo['country'] . "' \n");
        }

        if (isset($payerInfo['contacts']) && is_array($payerInfo['contacts'])) {
            //add contacts
            $contactTypesArray = ContactTypes::getContactTypesArray();
            $payerContact = new PayerContact();
            $contactTypeId = 0;

            foreach ($payerInfo['contacts'] as $contact => $value) {
                if ($override || $value) {
                    $contactTypeId = $contactTypesArray["$contact"];
                    if ($contactTypeId) {
                        $payerContact->loadWhere("payer_id = $payer->id AND contact_type_id = $contactTypeId ");
                        $payerContact->contact_type_id = $contactTypeId;
                        $payerContact->payer_id = $payer->id;
                        $payerContact->value = $value;
                        $payerContact->save();
                        $payerContact->clear();

                        if ($payerInfo['from_batch'] == 1) {
                            $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
                            if (file_exists($log))
                                $handler = fopen($log, 'a') or die("can't open file {$log} append");
                            else
                                $handler = fopen($log, 'w') or die("can't open file {$log} write");
                            fwrite($handler, "BATCH UPDATE '" . $contact . " : " . $value . "' \n");
                        }

                        if ($payerInfo['contactHome'] && $payerInfo['contactHome'] == $contact) {
                            $contactTypeId = $contactTypesArray["HOME_PHONE"];
                            $payerContact->loadWhere("payer_id = $payer->id AND contact_type_id = $contactTypeId ");
                            $payerContact->contact_type_id = $contactTypeId;
                            $payerContact->payer_id = $payer->id;
                            $payerContact->value = $value;
                            $payerContact->save();
                            $payerContact->clear();
                            $payerInfo['contacts']["HOME_PHONE"] = $value;
                        }
                    }
                }
            }

            if ($payerInfo['contacts']['EMAIL'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 11";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['DAY_PHONE_EXT'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 4";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['NIGHT_PHONE'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 5";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['NIGHT_PHONE_EXT'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 6";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['WORK_PHONE'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 15";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['WORK_PHONE_EXT'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 16";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['HOME_PHONE'] == "" && $ifnotbatch == 1) {
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 7";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['MOBILE'] == "" && $ifnotbatch == 1) { //For Bug 67153 comment #45
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 10";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['MOBILE_PROVIDER'] == "" && $ifnotbatch == 1) { //For Bug 67153 comment #45
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 17";
                $db->executeCommand($sqlQuery);
            }

            if ($payerInfo['contacts']['FAX'] == "" && $ifnotbatch == 1) { //For Bug 67153 comment #45
                $db = DBCon::instance();
                $sqlQuery = "DELETE FROM payer_contact_infos WHERE payer_id = $payer->id AND contact_type_id = 12";
                $db->executeCommand($sqlQuery);
            }
        }

        if ($payerInfo['payment_type'] == "credit_card" && $payerInfo['card_number'] && $payerInfo['card_type_id']) {
            // save credit card info
            // check Merchant Account first
            $client_session = new Zend_Session_Namespace(SESSION_CLIENT);
            $config = $client_session->config;
            if (!$config->payment->gateway_enabled) {
                FamilyAccountsUtility::saveCreditCardInfo($payer->id, $payerInfo);
            } else {
                if ($config->payment->payment_gateway == 'payline') {

                    // save to customer vault
                    $payerInfo['payer_id'] = $payer->id;
                    $payerInfo['action'] = 'add-customer';
                    $payerInfo['card_type'] = $payerInfo['card_type_id'];
                    $result = FamilyAccountsUtility::saveToCustomerVaultPayline($payerInfo, $config);
                } else {
                    FamilyAccountsUtility::saveCreditCardInfo($payer->id, $payerInfo);
                }
            }
        }

        if ($updatestudent && !$payerInfo['from_batch']) {
            $payerInfo['id'] = $studentid;

            FamilyAccountsUtility::saveStudentForFamilyAccount($familyAccountId, $payerInfo, false, true);
        }

        // update family account
        $mainpayer = Payers::getMainPayerFromFam($familyAccountId);

        if ($mainpayer == (int)$payerInfo['id'] && $mainpayer > 0) {
            $currentFamAccInfo = new FamilyAccount();
            $currentFamAccInfo->clear();
            $currentFamAccInfo->loadWhere('main_payer_id = ' . $mainpayer);
            $tmp = $currentFamAccInfo->toArray();
            $famAccountName = explode(", ", $tmp['account_name']); // last name, first name

            if (isset($payerInfo['first_name']) && $payerInfo['first_name']) {
                $famAccountName[1] = $payerInfo['first_name'];
            }

            if (isset($payerInfo['last_name']) && $payerInfo['last_name']) {
                $famAccountName[0] = $payerInfo['last_name'];
            }

            $currentFamAccInfo->account_name = implode(", ", $famAccountName);
            $currentFamAccInfo->save();
        }

        return $payer->id;
    }

    public function saveCreditCardInfo($payer_id = 0, $payer)
    {
        if (isset($payer['payment_type']) && $payer['payment_type'] = "credit_card") {
            $payerCreditCard = new PayerCreditCard();
            $payerCreditCard->first_name = $payer['card_first_name'];
            $payerCreditCard->last_name = $payer['card_last_name'];
            $payerCreditCard->card_number = substr($payer['card_number'], -4);
            $payerCreditCard->card_type_id = $payer['card_type_id'];
            $payerCreditCard->back_number = null;
            $payerCreditCard->enabled = 1;
            $payerCreditCard->share_cc = 1;
            $payerCreditCard->enabled = $payer['enabled'];
            $payerCreditCard->payer_id = $payer_id;
            $payerCreditCard->expiration = TimeUtility::convertToSQLDateFormat(date("m/d/Y", mktime(0, 0, 0, $payer['card_expiration_month'], 1, $payer['card_expiration_year'])));
            $payerCreditCard->address = $payer['address'];
            $payerCreditCard->address2 = $payer['address2'];
            $payerCreditCard->city = $payer['city'];
            $payerCreditCard->state = $payer['state'];
            $payerCreditCard->zip = $payer['zip'];
            $payerCreditCard->email = $payer['contacts']['EMAIL'];
            /*
             * Adding New Fields for ORG ACCT
             * */
            $payerCreditCard->foreign_zip = $payer['foreign_zip'];
            $payerCreditCard->country = $payer['country'];

            $payerCreditCard->save();
            if ($payerCreditCard->id) {
                $payerCardData = new PayerCardData();
                $payerCardData->saveCardData($payerCreditCard->id, $payer['user_id'], $payer['card_number'], $payer['sec_key']);
            }
        }
    }

    public function saveToCustomerVaultPayline($cardData, $conf, $public = 0)
    {
        /*
         * new behavior :
         * don't save to vault, IF user create new
         * credit card when make payment and choose NOT store
         * Credit Card for future payment
         */
        if (isset($cardData['from_payment_screen']) && $cardData['from_payment_screen'] && ($cardData['enabled'] == '' || empty($cardData['enabled']))) {
            FamilyAccountsUtility::saveCreditCardInformation($cardData, null);
            return true;
        }
        $http = explode(':', $_SERVER['HTTP_REFERER']);
        $http_protocol = ($http[0] == "") ? "http" : $http[0];
        $sitename = $http_protocol . '://' . $_SERVER['HTTP_HOST'];

        $customer = new Payline;
        $customer->APIKey = $conf->payline->key;
        $customer->transactionType = $cardData['action'];
        $customer->httpdReferrer = $_SERVER['HTTP_REFERER'];
        $customer->applicationSource = $conf->payline->app;
        $customer->siteName = $sitename;

        if ($cardData['id']) {
            $payer_card_id_sess = new Zend_Session_Namespace('payer_card_id');
            $payer_card_id_sess->id = $cardData['id'];
            $payerCreditCard = new PayerCreditCard();
            $payerCreditCard->loadWhere("id=" . $cardData['id']);
            if ($payerCreditCard->customer_vault_id != NULL || $payerCreditCard->customer_vault_id != "") {
                $customer->vaultId = $payerCreditCard->customer_vault_id;
            }
        }

        if (isset($cardData['customer_vault_id']) && $cardData['customer_vault_id'] != "") {
            $customer->vaultId = $cardData['customer_vault_id'];
        }

        // billing data
        $customer->billing['first-name'] = $cardData['first_name'];
        $customer->billing['last-name'] = $cardData['last_name'];
        $customer->billing['company'] = $cardData['company_name'];
        $customer->billing['address1'] = $cardData['address'];
        $customer->billing['address2'] = $cardData['address2'];
        $customer->billing['city'] = $cardData['city'];
        $customer->billing['state'] = (isset($cardData['state'])) ? $cardData['state'] : $cardData['state_old'];
        $customer->billing['zip'] = $cardData['zip'];
        $customer->billing['email'] = $cardData['email'];
        $customer->billing['country'] = "US";

        // card data
        if ((strlen($cardData['card_number']) == 4 || $cardData['card_number'] == '' || preg_match("/(XXXX-XXXX-XXXX)/i", $cardData['card_number'])) && (isset($cardData['customer_vault_id']) && $cardData['customer_vault_id']) != "") {
            // don't update card number
            $customer->order['cc-number'] = "";
        } else {
            $customer->order['cc-number'] = $cardData['card_number'];
        }
        $expiration_month = (isset($cardData['card_expiration_month'])) ? $cardData['card_expiration_month'] : $cardData['card_expiration_month_old'];
        $expiration_year = (isset($cardData['card_expiration_year'])) ? $cardData['card_expiration_year'] : $cardData['card_expiration_year_old'];
        // MMYY
        $cc_exp = $expiration_month . "" . $expiration_year;
        $customer->order['cc-exp'] = $cc_exp;

        $log = new BaseLogger();
        $log->logInfo("**** start saving to payline customer vault ****");

        if ($customer->doCustomerStep1()) {
            if ($customer->doCustomerStep3()) {
                // save non-sensitive information to database
                // and save customer vault id
                $cardid = FamilyAccountsUtility::saveCreditCardInformation($cardData, $customer->vaultId);

                $log->logInfo("**** information ****");
                $log->logInfo("result = " . $customer->result->result);
                $log->logInfo("code = " . $customer->result->code);
                $log->logInfo("text = " . $customer->result->text);
                $log->logInfo("msg = " . $customer->result->msg);
                $log->logInfo("**** end of information ****");

                return true;

            } else {

                $error_payline = new Zend_Session_Namespace('ERROR_PAYLINE');
                $error_payline->code = $customer->result->code;
                $error_payline->text = $customer->result->text;
                $error_payline->msg = $customer->result->msg;

                $log->logInfo("**** error saving to payline customer vault ****");
                $log->logInfo("**** information ****");
                $log->logInfo("result = " . $customer->result->result);
                $log->logInfo("code = " . $customer->result->code);
                $log->logInfo("text = " . $customer->result->text);
                $log->logInfo("msg = " . $customer->result->msg);
                $log->logInfo("**** end of information ****");

                return false;
            }
        }
    }

    public function saveCreditCardInformation($cardData, $customervaultid)
    {
        if ($cardData) {
            $payerCreditCard = new PayerCreditCard();
            if ($cardData['id']) {
                $payerCreditCard->loadWhere("id=" . $cardData['id']);
                $flag = 1;
            }
            $payerCreditCard->payer_id = (isset($cardData['payer_id'])) ? $cardData['payer_id'] : $cardData['payer_id_old'];
            $payerCreditCard->first_name = $cardData['first_name'];
            $payerCreditCard->last_name = $cardData['last_name'];
            $payerCreditCard->company_name = $cardData['company_name'];
            $cardNo = str_replace('-', '', $cardData['card_number']);
            $payerCreditCard->card_number = substr($cardNo, -4);
            $payerCreditCard->card_type_id = (isset($cardData['card_type'])) ? $cardData['card_type'] : $cardData['card_type_old'];
            $payerCreditCard->back_number = null; //set to null. We don't store this kind of information
            $payerCreditCard->share_cc = isset($cardData['share_cc']) ? $cardData['share_cc'] : ($cardData['share_cc_old'] ? $cardData['share_cc_old'] : 0);
            $payerCreditCard->enabled = isset($cardData['enabled']) && $cardData['enabled'] > 0 ? 1 : 0;
            $expiration_month = (isset($cardData['card_expiration_month'])) ? $cardData['card_expiration_month'] : $cardData['card_expiration_month_old'];
            $expiration_year = (isset($cardData['card_expiration_year'])) ? $cardData['card_expiration_year'] : $cardData['card_expiration_year_old'];
            $payerCreditCard->expiration = TimeUtility::convertToSQLDateFormat(date("m/d/Y", mktime(0, 0, 0, $expiration_month, 1, $expiration_year)));
            $payerCreditCard->address = $cardData['address'];
            $payerCreditCard->address2 = $cardData['address2'];
            $payerCreditCard->city = $cardData['city'];
            $payerCreditCard->state = (isset($cardData['state'])) ? $cardData['state'] : $cardData['state_old'];
            $payerCreditCard->zip = $cardData['zip'];
            $payerCreditCard->email = $cardData['email'];
            $payerCreditCard->customer_vault_id = $customervaultid;
            $payerCreditCard->foreign_zip = $cardData['foreign_zip'];
            $payerCreditCard->country = $cardData['country'];
            // add delete
            if (isset($cardData['deleted'])) {
                $payerCreditCard->deleted = $cardData['deleted'];
            }

            $payerCreditCard->name_on_card = $cardData['name_on_card'];

            $payerCreditCard->save();

            // get card id
            $payline_card = new Zend_Session_Namespace('PAYLINE_CARD');
            $payline_card->card_id = $payerCreditCard->id;
        }
    }

    function saveStudentForFamilyAccount($familyAccountId, $studentInfo, $custom_field = NULL, $includeOtherDetails = true, $override, $create_new = false)
    {

        $currentUserId = Utility_AuthUtility::getCurrentUserId();
        $updatepayer = false;
        $student = new Student();
        $studentId = (int)$studentInfo['id'];

        $studentInfo['from_batch'] = ($studentInfo['from_batch']) ? 1 : 0;

        if ($studentId) {
            $student->load($studentId);

            $payerstudent = Payers::studentAsPayer($studentId);
            if ($payerstudent[0]) {
                // if student is also a payer, check first if the payer is already deleted
                $temppayer = new Payer();
                $temppayer->loadWhere("id=" . $payerstudent[0]);

                if ($temppayer->id) {
                    $updatepayer = true;
                    $payerId = $payerstudent[0];
                }
            }
        } else {
            $student->created_on = date('Y-m-d');
        }

        if ($override) {

            $student->first_name = $studentInfo['first_name'];
            if (isset($studentInfo['middle_name']) && $studentInfo['middle_name'] != "") {
                $student->middle_name = $studentInfo['middle_name'];
            }

            if ($studentInfo['middle_name'] == "") {
                $student->middle_name = "";
            }
            $student->last_name = $studentInfo['last_name'];
            $student->gender = (int)$studentInfo['gender'];
            if (!$student->gender) {
                $genderId = 3; //default N/A
                $student->gender = $genderId;
            }
            if ($student->grade_level != $studentInfo['grade_level']) {
                $student->grade_changed = date("Y-m-d");
                $student->grade_changed_by_id = $currentUserId;
            }
            $student->grade_level = ($studentInfo['grade_level']) ? $studentInfo['grade_level'] : "";
            $student->custom_id = ($studentInfo['custom_id'] ? $studentInfo['custom_id'] : "");
            $student->school_id = (int)$studentInfo['school_id'];
            if ($studentInfo['price_tier_id'] == NULL || $studentInfo['price_tier_id'] == '') {
                $student->price_tier_id = NULL;
            } else {
                $student->price_tier_id = (int)$studentInfo['price_tier_id'];
            }

            $student->age_group_id = ($studentInfo['from_batch']) ? $student->age_group_id : (int)$studentInfo['age_group_id'];

            // s_id - new column on students table to hold data for Student ID on screen when adding/editing student

            if (isset($studentInfo['student_id']) && $studentInfo['student_id'] != "") {
                $student->s_id = $studentInfo['student_id'];
            }

            if ($studentInfo['student_id'] == "") {
                $student->s_id = NULL;
            }
        } else {

            if (isset($studentInfo['first_name']) && $studentInfo['first_name']) {
                $student->first_name = $studentInfo['first_name'];
            }

            if (isset($studentInfo['middle_name']) && $studentInfo['middle_name'] != "") {
                $student->middle_name = $studentInfo['middle_name'];
            }


            if ($studentInfo['middle_name'] == "" && $studentInfo['from_batch'] == 0) { //For Bug 67153 comment #42
                $student->middle_name = "";
            }

            if (isset($studentInfo['last_name']) && $studentInfo['last_name']) {
                $student->last_name = $studentInfo['last_name'];
            }

            if (isset($studentInfo['gender']) && $studentInfo['gender']) {
                $student->gender = $studentInfo['gender'];
            } else {
                if (!$student->gender) {
                    $genderId = 3; //default N/A
                    $student->gender = $genderId;
                }
            }

            if (isset($studentInfo['grade_level']) && $studentInfo['grade_level']) {
                if ($student->grade_level != $studentInfo['grade_level']) {
                    $student->grade_changed = date("Y-m-d");
                    $student->grade_changed_by_id = $currentUserId;
                }
                $student->grade_level = $studentInfo['grade_level'];
            }

            if (isset($studentInfo['custom_id']) && $studentInfo['custom_id']) {
                $student->custom_id = $studentInfo['custom_id'];
            } else {
                if ($student->custom_id == NULL) {
                    $student->custom_id = "";
                }
            }

            if (isset($studentInfo['school_id'])) {
                $student->school_id = (int)$studentInfo['school_id'];
            } else {
                if ($student->school_id == NULL) {
                    $student->school_id = (int)$studentInfo['school_id'];
                }
            }

            if (isset($studentInfo['price_tier_id'])) {
                if ($studentInfo['price_tier_id'] == NULL || $studentInfo['price_tier_id'] == '') {
                    $student->price_tier_id = NULL;
                } else {
                    $student->price_tier_id = (int)$studentInfo['price_tier_id'];
                }
            } else {
                if ($student->price_tier_id == NULL) {
                    if ($studentInfo['price_tier_id'] == NULL || $studentInfo['price_tier_id'] == '') {
                        $student->price_tier_id = NULL;
                    } else {
                        $student->price_tier_id = (int)$studentInfo['price_tier_id'];
                    }
                }
            }

            if (isset($studentInfo['age_group_id'])) {
                $student->age_group_id = (int)$studentInfo['age_group_id'];
            } else {
                if ($student->age_group_id == NULL) {
                    $student->age_group_id = (int)$studentInfo['age_group_id'];
                }
            }

            if (isset($studentInfo['student_id']) && $studentInfo['student_id'] != "") {
                $student->s_id = $studentInfo['student_id'];
            }

            if ($studentInfo['student_id'] == "" && $studentInfo['from_batch'] == 0) { //For Bug 67153 comment #42
                $student->s_id = NULL;
            }
        }

        if (!$student->school_id) {
            $student->school_id = NULL;
        }

        if (!$studentInfo['from_batch'])
            $student->disability = ($studentInfo['disability']) ? $studentInfo['disability'] : 0;
        $student->created_by_id = Utility_AuthUtility::getCurrentUserId();;

        /*
         * Reason to comment: not compatible with all clients.
         * */
        if (is_array($custom_field) && $custom_field != null) {
            foreach ($custom_field as $key => $item) {
                if ($key == 50) {
                    $email_preference = ($item[1]) ? $item[1] : 0;
                }
            }
        } elseif (!empty($studentInfo["custom_field"][50]) && count($studentInfo["custom_field"][50]) > 1) {
            if ($studentInfo["custom_field"][50]["registration_form_item_id"][0] == 25)
                $email_preference = ($studentInfo["custom_field"][50]["registration_form_item_id"][1]) ? 1 : 0;
        } else {
        	$email_preference = ($studentInfo['email_preference']) ? $studentInfo['email_preference'] : 0;
        }

		$student->email_preference = $email_preference;

	        if ($studentInfo['studentpayer'])
                $student->payer_id=$studentInfo['studentpayer'];

            $student->save();

			if ($studentInfo['from_batch'] == 1){
				$log = CLIENT_DIR."logs/".date("Ymd")."_batch_update.log";
			        if (file_exists($log))
			            $handler = fopen($log, 'a') or die("can't open file {$log} append");
			        else
			            $handler = fopen($log, 'w') or die("can't open file {$log} write");
			        fwrite($handler, "-- BATCH UPDATE \n");
			        fwrite($handler, "BATCH UPDATE date: '".date("F j, Y, g:i a")."' \n");
			        fwrite($handler, "BATCH UPDATE user_id: '".$currentUserId."' \n");
			        fwrite($handler, "BATCH UPDATE family_account_id: '".$familyAccountId."' \n");
			        fwrite($handler, "BATCH UPDATE student_id: '".$studentInfo['id']."' \n");
			        fwrite($handler, "BATCH UPDATE first_name: '".$studentInfo['first_name']."' \n");
			        fwrite($handler, "BATCH UPDATE last_name: '".$studentInfo['last_name']."' \n");
			}

            if ($familyAccountId) {
    	        $familyStudent = new FamilyAccountStudent();
    	        $familyStudent->loadWhere("family_account_id = " . $familyAccountId . " AND student_id = " . $student->id);
    	        $familyStudent->family_account_id = $familyAccountId;
    	        $familyStudent->student_id = $student->id;
    	        $familyStudent->save();
            }

	        if ($includeOtherDetails){
	            //student details
	            $studentDetail = new StudentDetail();
	            $studentDetail->loadWhere("student_id = " . $student->id);
	            $studentDetail->student_id = $student->id;
	            $studentDetail->nationality = ( $studentInfo['nationality'] ? $studentInfo['nationality'] : "");
	            $birthdate = ( $studentInfo['birthdate'] ? TimeUtility::convertToSQLDateFormat($studentInfo['birthdate']) : "");
	            $studentDetail->birthdate = $birthdate;
	            $studentDetail->height = (float)$studentInfo['height'];
	            $studentDetail->weight = (float)$studentInfo['weight'];

	            $physicaldate = ( $studentInfo['physical_date'] ? TimeUtility::convertToSQLDateFormat($studentInfo['physical_date']) : "");
	            $studentDetail->physical_date = $physicaldate;

	            $studentDetail->instrument = ( $studentInfo['instrument'] ? $studentInfo['instrument'] : "");
	            $studentDetail->swimming_level = ( $studentInfo['swimming_level'] ? $studentInfo['swimming_level'] : "");
	            $studentDetail->shirt_size_id = (int)$studentInfo['shirt_size_id'];
	            $studentDetail->classroom_teacher = ( $studentInfo['classroom_teacher'] ? $studentInfo['classroom_teacher'] : "");
	            $studentDetail->notes = ( $studentInfo['notes'] ? $studentInfo['notes'] : "");
                $studentDetail->driver_license = ( $studentInfo['driver_license'] ? $studentInfo['driver_license'] : "");
                $studentDetail->short_size_id = (int)$studentInfo['short_size_id'];
				$studentDetail->rural_resident = (int)$studentInfo['rural_resident'];
				$studentDetail->bus_transpo_needed = (int)$studentInfo['bus_transpo_needed'];
				$studentDetail->bus_number = ( $studentInfo['bus_number'] ? $studentInfo['bus_number'] : null);
	            $studentDetail->save();

	            //pickup person
	            if(isset($studentInfo['pickup_person'])){
	                $studentPickupPerson = new StudentPickupPerson();
	                $studentPickupPerson->loadByStudentId($student->id);
	                $studentPickupPerson->first_name = $studentInfo['pickup_person'];
	                $studentPickupPerson->last_name = "";
	                $studentPickupPerson->relation = ($studentInfo['pickup_person_relation']) ? $studentInfo['pickup_person_relation'] : "";
	                $studentPickupPerson->student_id = $student->id;
	                $studentPickupPerson->save();
	            }

                // save custom detail
                if(isset($studentInfo['custom_field']) && count($studentInfo['custom_field']) > 0) {
                    $studentcustominfo = new StudentRegistrationFormInfo();
                    foreach ($studentInfo['custom_field'] as $key_custom => $field) {
                        if (isset($field['registration_form_item_id']) && count($field['registration_form_item_id']) > 0 && is_array($field['registration_form_item_id'])) {
                            $frm_itm_id = $field['registration_form_item_id'];
                            foreach ($frm_itm_id as $frm_itm => $frmitem) {
                                $studentcustominfo->clear();
                                $studentcustominfo->student_id = $student->id;
                                $studentcustominfo->registration_form_id = $key_custom;
                                if ($frmitem == 0 || $frmitem == '') {
                                    $studentcustominfo->registration_form_item_id = NULL;
                                } else {
                                    $studentcustominfo->registration_form_item_id = $frmitem;
                                }
                                $studentcustominfo->save();
                            }
                        } else {
                            $studentcustominfo->clear();
                            $studentcustominfo->loadWhere('student_id='.$student->id. " AND registration_form_id=".$key_custom);
                            $studentcustominfo->student_id = $student->id;
                            $studentcustominfo->registration_form_id = $key_custom;
                            if ($field['registration_form_item_id'] == 0 || $field['registration_form_item_id'] == '') {
                                $studentcustominfo->registration_form_item_id = NULL;
                            } else {
                                $studentcustominfo->registration_form_item_id = $field['registration_form_item_id'];
                            }

                            if (isset($field['value'])) {
                                $studentcustominfo->value = $field['value'];
                            }
                            $studentcustominfo->save();
                        }
                    }
                }
	        }

	        //student address
	        $studentAddress = new StudentAddress();

	        if ($override){
	            $studentAddressId = (int)$studentInfo['address_id'];
	            if ($studentAddressId){
	                $studentAddress->load($studentAddressId);
	            }else{
	                $studentAddress->loadWhere("student_id = " . $student->id . " AND primary_address = " . (int)$studentInfo['main_address']);
	            }
	            $studentAddress->address_type_id = ($studentInfo['address_type_id']) ? $studentInfo['address_type_id'] : 0;
	            $studentAddress->primary_address = ($studentInfo['main_address']) ? $studentInfo['main_address'] : 0;
	            $studentAddress->address = ($studentInfo['address']) ? $studentInfo['address'] : '';
            if (isset($studentInfo['address2']) && $studentInfo['address2'] != "") {
                $studentAddress->address2 = $studentInfo['address2'];
            }

            if ($studentInfo['address2'] == "") {
                $studentAddress->address2 = "";
            }
            $studentAddress->city = ($studentInfo['city']) ? $studentInfo['city'] : '';
            $studentAddress->state = ($studentInfo['state']) ? $studentInfo['state'] : '';
            $studentAddress->zip = ($studentInfo['zip']) ? $studentInfo['zip'] : '';
            $studentAddress->foreign_zip = ($studentInfo['foreign_zip']) ? $studentInfo['foreign_zip'] : '';
            $studentAddress->country = ($studentInfo['country']) ? $studentInfo['country'] : '';

        } else {
            $studentAddress->loadWhere("student_id = " . $student->id . " AND primary_address = " . (int)$studentInfo['main_address']);

            if (isset($studentInfo['address_type_id']) && $studentInfo['address_type_id']) {
                $studentAddress->address_type_id = $studentInfo['address_type_id'];
            }

            if (isset($studentInfo['main_address']) && $studentInfo['main_address']) {
                $studentAddress->primary_address = $studentInfo['main_address'];
            }

            if (isset($studentInfo['address']) && $studentInfo['address'] != "") {
                $studentAddress->address = $studentInfo['address'];
            }

            if ($studentInfo['address'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->address = "";
            }

            if (isset($studentInfo['address2']) && $studentInfo['address2'] != "") {
                $studentAddress->address2 = $studentInfo['address2'];
            }

            if ($studentInfo['address2'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->address2 = "";
            }

            if (isset($studentInfo['city']) && $studentInfo['city'] != "") {
                $studentAddress->city = $studentInfo['city'];
            }

            if ($studentInfo['city'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->city = "";
            }

            if (isset($studentInfo['state']) && $studentInfo['state'] != "") {
                $studentAddress->state = $studentInfo['state'];
            }

            if ($studentInfo['state'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->state = "";
            }

            if (isset($studentInfo['zip']) && $studentInfo['zip'] != "") {
                $studentAddress->zip = $studentInfo['zip'];
            }

            if ($studentInfo['zip'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->zip = "";
            }

            if (isset($studentInfo['foreign_zip']) && $studentInfo['foreign_zip'] != "") {
                $studentAddress->foreign_zip = $studentInfo['foreign_zip'];
            }

            if ($studentInfo['foreign_zip'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->foreign_zip = "";
            }

            if (isset($studentInfo['country']) && $studentInfo['country'] != "") {
                $studentAddress->country = $studentInfo['country'];
            }

            if ($studentInfo['country'] == "" && $studentInfo['from_batch'] == 0) {
                $studentAddress->country = "";
            }
        }
        $studentAddress->student_id = $student->id;
        $studentAddress->save();

        if ($studentInfo['from_batch'] == 1) {
            $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
            if (file_exists($log))
                $handler = fopen($log, 'a') or die("can't open file {$log} append");
            else
                $handler = fopen($log, 'w') or die("can't open file {$log} write");
            fwrite($handler, "BATCH UPDATE address: '" . $studentInfo['address'] . "' \n");
            fwrite($handler, "BATCH UPDATE address2: '" . $studentInfo['address2'] . "' \n");
            fwrite($handler, "BATCH UPDATE city: '" . $studentInfo['city'] . "' \n");
            fwrite($handler, "BATCH UPDATE state: '" . $studentInfo['state'] . "' \n");
            fwrite($handler, "BATCH UPDATE zip: '" . $studentInfo['zip'] . "' \n");
            fwrite($handler, "BATCH UPDATE foreign_zip: '" . $studentInfo['foreign_zip'] . "' \n");
            fwrite($handler, "BATCH UPDATE country: '" . $studentInfo['country'] . "' \n");
        }

        if (isset($studentInfo['contacts']) && is_array($studentInfo['contacts'])) {
            //add contacts
            $contactTypesArray = ContactTypes::getContactTypesArray();
            $studentContact = new StudentContactInfo();
            $contactTypeId = 0;
            foreach ($studentInfo['contacts'] as $contact => $value) {
                if ($override || $value) {

                    $contactTypeId = $contactTypesArray["$contact"];
                    $studentContact->loadWhere("student_id = " . $student->id . " AND contact_type_id = $contactTypeId ");
                    $studentContact->contact_type_id = $contactTypeId;
                    $studentContact->student_id = $student->id;
                    $studentContact->value = $value;
                    $studentContact->save();
                    $studentContact->clear();

                    if ($studentInfo['from_batch'] == 1) {
                        $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
                        if (file_exists($log))
                            $handler = fopen($log, 'a') or die("can't open file {$log} append");
                        else
                            $handler = fopen($log, 'w') or die("can't open file {$log} write");
                        fwrite($handler, "BATCH UPDATE '" . $contact . " : " . $value . "' \n");
                    }

                }
            }
        }

        if ($studentInfo['contacts']['EMAIL'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 11";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['DAY_PHONE'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 3";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['DAY_PHONE_EXT'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 4";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['NIGHT_PHONE'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 5";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['NIGHT_PHONE_EXT'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 6";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['WORK_PHONE'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 15";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['WORK_PHONE_EXT'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 16";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['HOME_PHONE'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 7";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['MOBILE'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 10";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['MOBILE_PROVIDER'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 17";
            $db->executeCommand($sqlQuery);
        }

        if ($studentInfo['contacts']['FAX'] == "" && $studentInfo['from_batch'] == 0) {
            $db = DBCon::instance();
            $sqlQuery = "DELETE FROM student_contact_infos WHERE student_id = $student->id AND contact_type_id = 12";
            $db->executeCommand($sqlQuery);
        }
        //contacts

        $studentContact = new StudentContactInfo();
        $phonevar = "";
        $phonevardesc = "";
        $phonevarid = "";
        $phoneId = 0;

        for ($i = 1; $i <= 4; $i++) {
            $phonevar = "phone_$i";
            $phonevardesc = "phone_$i" . "_desc";
            $phonevarid = "phone_idn_$i";
            $phoneId = (int)$studentInfo["$phonevarid"];
            if (isset($studentInfo["$phonevar"]) && empty($studentInfo["$phonevar"])) {
                $studentInfo["$phonevar"] = " ";
            }

            if (isset($studentInfo["$phonevar"])) {
                $studentContact->clear();
                if ($phoneId) {
                    $studentContact->loadWhere("id = $phoneId");
                }
                $studentContact->contact_type_id = $contactTypesArray[ContactType::CODE_OTHERPHONE];
                $studentContact->value = $studentInfo["$phonevar"];
                $studentContact->purpose = $studentInfo["$phonevardesc"];
                $studentContact->primary_contact = 0;
                $studentContact->student_id = $student->id;
                $studentContact->save();
            }
        }

        if (!$studentInfo['from_batch'])
            StudentCustomerProfiles::deleteByStudentID($studentId);

        if (isset($studentInfo['student_profiles']) && is_array($studentInfo['student_profiles'])) {
            //add student profiles
            $studentProfile = new StudentCustomerProfile();
            foreach ($studentInfo['student_profiles'] as $key => $value) {
                $studentProfile->loadWhere("student_id = " . $student->id . " AND customer_profile_id = $value ");
                $studentProfile->customer_profile_id = $value;
                $studentProfile->student_id = $student->id;
                $studentProfile->save();
                $studentProfile->clear();
            }
        }

        if ($updatepayer && !$studentInfo['from_batch'] && $familyAccountId) {
            $studentInfo['id'] = $payerId;
            FamilyAccountsUtility::savePayerForSync($familyAccountId, $studentInfo);
        }

        return $student->id;
    }

    function savePayerForSync($familyAccountId, $payerInfo)
    {
        $payer = new Payer();
        $payerId = (int)$payerInfo['id'];
        if ($payerId) {
            $payer->load($payerId);
            $payer->first_name = $payerInfo['first_name'];
            $payer->middle_name = $payerInfo['middle_name'];
            $payer->last_name = $payerInfo['last_name'];
            $payer->age_group_id = ($payerInfo['from_batch']) ? $payer->age_group_id : (int)$payerInfo['age_group_id'];
            if (isset($payerInfo['disability'])) {
                $payer->disability = $payerInfo['disability'];
            } else {
                $payer->disability = 0;
            }

            if (isset($payerInfo['email_preference'])) {
                $payer->email_preference = 1;
            } else {
                $payer->email_preference = 0;
            }

            if (isset($payerInfo['comments'])) {
                $payer->comments = $payerInfo['comments'];
            }
            if (isset($payerInfo['price_tier_id'])) {
                if ($payerInfo['price_tier_id'] == NULL || $payerInfo['price_tier_id'] == '') {
                    $payer->price_tier_id = NULL;
                } else {
                    $payer->price_tier_id = (int)$payerInfo['price_tier_id'];
                }
            }
            $payer->save();
        }


        $familyAccount = new FamilyAccount();
        $familyAccount->load($familyAccountId);
        if ($familyAccount->main_payer_id == $payerId) {
            $familyAccount->account_name = $payerInfo['last_name'] . ', ' . $payerInfo['first_name'];
            $familyAccount->save();
        }

        $payerAddress = new PayerAddress();
        $payerAddressId = (int)$payerInfo['address_id'];
        if ($payerAddressId) {
            $payerAddress->load($payerAddressId);
        } else {
            $payerAddress->loadWhere("payer_id = " . $payer->id . " AND primary_address = " . (int)$payerInfo['main_address']);
        }
        if ($payerInfo['address_type_id'] == NULL)
            die;
        $payerAddress->address_type_id = $payerInfo['address_type_id'];
        $payerAddress->primary_address = $payerInfo['main_address'];
        $payerAddress->address = $payerInfo['address'];
        $payerAddress->address2 = $payerInfo['address2'];
        $payerAddress->city = $payerInfo['city'];
        $payerAddress->state = $payerInfo['state'];
        $payerAddress->zip = $payerInfo['zip'];
        $payerAddress->foreign_zip = $payerInfo['foreign_zip'];
        $payerAddress->country = $payerInfo['country'];
        $payerAddress->payer_id = $payer->id;
        $payerAddress->save();

        if (isset($payerInfo['contacts']) && is_array($payerInfo['contacts'])) {
            $contactTypesArray = ContactTypes::getContactTypesArray();
            $payerContact = new PayerContact();
            $contactTypeId = 0;
            foreach ($payerInfo['contacts'] as $contact => $value) {
                if ($value) {
                    $contactTypeId = $contactTypesArray["$contact"];
                    if ($contactTypeId) {
                        $payerContact->loadWhere("payer_id = $payer->id AND contact_type_id = $contactTypeId ");
                        $payerContact->contact_type_id = $contactTypeId;
                        $payerContact->payer_id = $payer->id;
                        $payerContact->value = $value;
                        $payerContact->save();
                        $payerContact->clear();
                    }
                }
            }

        }
        return $payer->id;
    }

    function saveStudentForRSSFamilyAccount($familyAccountId, $studentInfo, $includeOtherDetails = true, $override = true)
    {
        $currentUserId = Utility_AuthUtility::getCurrentUserId();

        $student = new Student();
        $studentId = (int)$studentInfo['id'];
        if ($studentId) {
            $student->load($studentId);
        }
        $student->first_name = $studentInfo['first_name'];
        $student->middle_name = $studentInfo['middle_name'];
        $student->last_name = $studentInfo['last_name'];
        $student->grade_level = $studentInfo['grade_level'];
        $student->school_id = (int)$studentInfo['school_id'];
        $student->custom_id = ($studentInfo['custom_id'] ? $studentInfo['custom_id'] : "");
        $student->created_by_id = ($currentUserId) ? $currentUserId : NULL;
        $student->save();

        $familyStudent = new FamilyAccountStudent();
        $familyStudent->loadWhere("family_account_id = " . $familyAccountId . " AND student_id = " . $student->id);
        $familyStudent->family_account_id = $familyAccountId;
        $familyStudent->student_id = $student->id;
        $familyStudent->save();
    }

    function updateCartStudentInfo($student_id)
    {
        $student = new Student();
        $studentDetails = new StudentDetail();
        $ageGroup = new AgeGroup();

        $cu = new CartUtility();
        $parts = array("cart", "wait", "approval");
        foreach ($parts as $part) {
            $update = false;
            foreach ($cu->{$part}->list as $idx => $cl) {
                if ($cu->{$part}->list[$idx]['data']) {
                    if ($cu->{$part}->list[$idx]['data']['student']->id == $student_id) {
                        $update = true;
                        $student->clear();
                        $student->load($student_id);
                        $studentDetails->clear();
                        $studentDetails->loadWhere('student_id = ' . $student_id);
                        $ageGroup->clear();
                        $ageGroup->load($student->age_group_id);
                        $aStudent = $student->toArray();
                        $aStudent['birthdate'] = $studentDetails->birthdate != '1970-01-01' ? $studentDetails->birthdate : "";
                        $aStudent['rangeAge1'] = $ageGroup->from_age;
                        $aStudent['rangeAge2'] = $ageGroup->to_age;
                        $aStudent['ssf_parameter_type_id'] = $cl['data']['student']->ssf_parameter_type_id;
                        $dataStudent = (object)$aStudent;
                        $cu->{$part}->list[$idx]['data']['student'] = $dataStudent;
                    }
                    foreach ($cu->{$part}->list[$idx]['data']['class']->participants as $key => $item) {
                        if ($item->student->id == $student_id) {
                            $update = true;
                            $student->clear();
                            $student->load($student_id);
                            $studentDetails->clear();
                            $studentDetails->loadWhere('student_id = ' . $student_id);
                            $ageGroup->clear();
                            $ageGroup->load($student->age_group_id);
                            $aStudent = $student->toArray();
                            $aStudent['birthdate'] = $studentDetails->birthdate != '1970-01-01' ? $studentDetails->birthdate : "";
                            $aStudent['rangeAge1'] = $ageGroup->from_age;
                            $aStudent['rangeAge2'] = $ageGroup->to_age;
                            $aStudent['ssf_parameter_type_id'] = $item->student->ssf_parameter_type_id;
                            $dataStudent = (object)$aStudent;
                            $cu->{$part}->list[$idx]['data']['class']->participants[$key]->student = $dataStudent;
                        }
                    }
                }

            }
            if ($update) {
                $cu->selfUpdate($part);
            }
        }
    }

    function saveGuardian($familyAccountId, $info, $fromPayer = false, $override)
    {
        $currentUserId = Utility_AuthUtility::getCurrentUserId();

        $guardian = new Guardian();

        if ($fromPayer) {
            $guardianId = (int)$info['guardian_id'];
            if ($guardianId) {
                $guardian->load($guardianId);
            }
            $guardian->payer_id = (int)$info['id'];
        } else {
            $guardianId = (int)$info['id'];
            if ($guardianId) {
                $guardian->load($guardianId);
            }
        }

        if ($override) {
            $guardian->first_name = $info['first_name'];
            $guardian->middle_name = $info['middle_name'];
            $guardian->last_name = $info['last_name'];

            if (isset($info['payer_id'])) {
                $guardian->payer_id = (int)$info['payer_id'];
            }
        } else {
            if (isset($info['first_name']) && $info['first_name']) {
                $guardian->first_name = $info['first_name'];
            }
            if (isset($info['middle_name']) && $info['middle_name']) {
                $guardian->middle_name = $info['middle_name'];
            }
            if (isset($info['last_name']) && $info['last_name']) {
                $guardian->last_name = $info['last_name'];
            }
            if (isset($info['payer_id']) && $info['payer_id']) {
                $guardian->payer_id = (int)$info['payer_id'];
            }
        }
        $guardian->created_by_id = Utility_AuthUtility::getCurrentUserId() ? Utility_AuthUtility::getCurrentUserId() : 1;
        $guardian->save();

        if ($info['from_batch'] == 1) {
            $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
            if (file_exists($log))
                $handler = fopen($log, 'a') or die("can't open file {$log} append");
            else
                $handler = fopen($log, 'w') or die("can't open file {$log} write");
            fwrite($handler, "-- BATCH UPDATE \n");
            fwrite($handler, "BATCH UPDATE date: '" . date("F j, Y, g:i a") . "' \n");
            fwrite($handler, "BATCH UPDATE user_id: '" . $currentUserId . "' \n");
            fwrite($handler, "BATCH UPDATE family_account_id: '" . $familyAccountId . "' \n");
            fwrite($handler, "BATCH UPDATE guardian_id: '" . $info['id'] . "' \n");
            fwrite($handler, "BATCH UPDATE first_name: '" . $info['first_name'] . "' \n");
            fwrite($handler, "BATCH UPDATE last_name: '" . $info['last_name'] . "' \n");
        }

        $familyGuardian = new FamilyAccountGuardian();
        $familyGuardian->loadWhere("family_account_id = " . $familyAccountId .
            " AND guardian_id = " . $guardian->id);
        $familyGuardian->family_account_id = $familyAccountId;
        $familyGuardian->guardian_id = $guardian->id;

        $familyGuardian->primary_contact_person = isset($info['primary_contact_person']) ? (int)$info['primary_contact_person'] : 0;

        $familyGuardian->save();

        $guardianAddress = new GuardianAddress();

        if ($override) {
            if ($fromPayer) {
                $guardianAddress->loadWhere("guardian_id = " . $guardian->id . " AND primary_address = " . (int)$info['main_address']);
            } else {
                $guardianAddressId = (int)$info['address_id'];
                if ($guardianAddressId) {
                    $guardianAddress->load($guardianAddressId);
                }
            }

            $guardianAddress->address_type_id = $info['address_type_id'];
            $guardianAddress->primary_address = $info['main_address'];
            $guardianAddress->address = $info['address'];
            $guardianAddress->address2 = $info['address2'];
            $guardianAddress->city = $info['city'];
            $guardianAddress->state = $info['state'];
            $guardianAddress->zip = $info['zip'];

        } else {
            $guardianAddress->loadWhere("guardian_id = " . $guardian->id . " AND primary_address = " . (int)$info['main_address']);

            if (isset($info['address_type_id']) && $info['address_type_id']) {
                $guardianAddress->address_type_id = $info['address_type_id'];
            }

            if (isset($info['main_address']) && $info['main_address']) {
                $guardianAddress->primary_address = $info['main_address'];
            }

            if (isset($info['address']) && $info['address']) {
                $guardianAddress->address = $info['address'];
            }

            if (isset($info['address2']) && $info['address2']) {
                $guardianAddress->address2 = $info['address2'];
            }

            if (isset($info['city']) && $info['city']) {
                $guardianAddress->city = $info['city'];
            }

            if (isset($info['state']) && $info['state']) {
                $guardianAddress->state = $info['state'];
            }

            if (isset($info['zip']) && $info['zip']) {
                $guardianAddress->zip = $info['zip'];
            }
        }
        $guardianAddress->guardian_id = $guardian->id;
        $guardianAddress->save();

        if ($info['from_batch'] == 1) {
            $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
            if (file_exists($log))
                $handler = fopen($log, 'a') or die("can't open file {$log} append");
            else
                $handler = fopen($log, 'w') or die("can't open file {$log} write");
            fwrite($handler, "BATCH UPDATE address: '" . $info['address'] . "' \n");
            fwrite($handler, "BATCH UPDATE address2: '" . $info['address2'] . "' \n");
            fwrite($handler, "BATCH UPDATE city: '" . $info['city'] . "' \n");
            fwrite($handler, "BATCH UPDATE state: '" . $info['state'] . "' \n");
            fwrite($handler, "BATCH UPDATE zip: '" . $info['zip'] . "' \n");
        }

        if (isset($info['contacts']) && is_array($info['contacts'])) {
            //add contacts
            $contactTypesArray = ContactTypes::getContactTypesArray();

            $guardianContact = new GuardianContact();
            $contactTypeId = 0;
            foreach ($info['contacts'] as $contact => $value) {
                if ($override || $value) {
                    $contactTypeId = $contactTypesArray["$contact"];
                    $guardianContact->loadWhere("guardian_id = " . $guardian->id . " AND contact_type_id = $contactTypeId ");
                    $guardianContact->contact_type_id = $contactTypeId;
                    $guardianContact->guardian_id = $guardian->id;
                    $guardianContact->value = $value;
                    $guardianContact->save();
                    $guardianContact->clear();

                    if ($info['from_batch'] == 1) {
                        $log = CLIENT_DIR . "logs/" . date("Ymd") . "_batch_update.log";
                        if (file_exists($log))
                            $handler = fopen($log, 'a') or die("can't open file {$log} append");
                        else
                            $handler = fopen($log, 'w') or die("can't open file {$log} write");
                        fwrite($handler, "BATCH UPDATE '" . $contact . " : " . $value . "' \n");
                    }

                }
            }
        }

        return $guardian->id;
    }

    function removeStudentsFromFamilyAccount($familyAccountId, $students)
    {
        if (is_array($students) && count($students)) {
            $studentsToDelete = implode(",", $students);

            $db = DBCon::instance();

            $sqlQuery = "UPDATE " . FamilyAccountStudent::TABLE_NAME .
                " SET deleted = 1 WHERE family_account_id = $familyAccountId AND student_id IN ($studentsToDelete)";
            $result = $db->executeCommand($sqlQuery);
            //check if student has family -- if none, soft-delete
            $student = new Student();
            $studentFamily = new FamilyAccountStudents();
            foreach ($students as $studentId) {
                $studentFamily->clear();
                $studentFamily->loadWhere("family_account_id = $familyAccountId AND student_id = $studentId AND deleted = 0 ");
                if ($studentFamily->count() == 0) {
                    $student->clear();
                    $student->loadById($studentId);
                    if ($student->id) {
                        $student->deleted = 1;
                        $student->save();
                    }
                }
            }
        }
    }

    //TODO - need more test here

    function removePayersFromFamilyAccount($familyAccountId, $payers)
    {
        if (is_array($payers) && count($payers)) {
            $payersToDelete = implode(",", $payers);

            $db = DBCon::instance();

            //delete family - student relationship
            $sqlQuery = "UPDATE " . FamilyAccountPayer::TABLE_NAME .
                " SET deleted = 1 WHERE family_account_id = $familyAccountId AND payer_id IN ($payersToDelete)";
            $result = $db->executeCommand($sqlQuery);

            //check if payer has family -- if none, soft-delete
            $payer = new Payer();
            $payerFamily = new FamilyAccountPayers();
            foreach ($payers as $payerId) {
                $payerFamily->clear();
                $payerFamily->loadWhere("family_account_id = $familyAccountId AND payer_id = $payerId AND deleted = 0 ");
                if ($payerFamily->count() == 0) {
                    $payer->clear();
                    $payer->load($payerId);
                    if ($payer->id) {

                        //check if payer has payments -- if yes, disable, none, delete
                        if (Payers::getPaymentCount($payerId)) {
                            $payer->enabled = 0;
                        } else {
                            $payer->deleted = 1;
                        }
                        $payer->save();
                    }
                }
            }
        }
    }

    function mergeStudents($studentIds, $mainStudentId, $participant = 0)
    {
        if (in_array($mainStudentId, $studentIds)) {
            $index = array_search($mainStudentId, $studentIds);
            if ($index >= 0 && $index < count($studentIds)) {
                unset($studentIds[$index]);
            }
        }

        // prevention updating all registration_student
        if (!$studentIds) return false;

        $params = array("match" => $studentIds);
        $students = Students::getStudentsInfo($params);

        $mainStudent = new Student();
        $mainStudent->load($mainStudentId);
        if ($mainStudent->id) {

            if (!$participant){
                $mainStudentDetail = new StudentDetail();
                $mainStudentDetail->loadWhere("student_id = " . $mainStudent->id);
                $mainStudentDetail->student_id = $mainStudent->id;
            }
                
            $mainStudentPrimaryAddress = new StudentAddress();
            $mainStudentPrimaryAddress->loadWhere("student_id = " . $mainStudent->id . " AND primary_address = 1 ");
            $mainStudentPrimaryAddress->student_id = $mainStudent->id;
            $mainStudentPrimaryAddress->primary_address = 1;

            $studentContact = new StudentContactInfo();

            $studentPickup = new StudentPickupPerson();

            foreach ($students as $eachStudent) {

                //update registration_students
                RegistrationStudents::replaceStudentInRegistration($eachStudent->id, $mainStudent->id);

                //update rss specific (order_lunch_charges, order_activity_payments)
                //copy student info, addresses, details, contacts, if info does not exist
                $mainStudent->gender = ($mainStudent->gender ? $mainStudent->gender : $eachStudent->gender);
                $mainStudent->age_group_id = ($mainStudent->age_group_id ? $mainStudent->age_group_id : $eachStudent->age_group_id);
                $mainStudent->price_tier_id = ($mainStudent->price_tier_id ? $mainStudent->price_tier_id : $eachStudent->price_tier_id);
                $mainStudent->school_id = ($mainStudent->school_id ? $mainStudent->school_id : $eachStudent->school_id);
                $mainStudent->grade_level = ($mainStudent->grade_level ? $mainStudent->grade_level : $eachStudent->grade_level);
                $mainStudent->status = ($mainStudent->status ? $mainStudent->status : $eachStudent->status);
                $mainStudent->disabled = ($mainStudent->disabled ? $mainStudent->disabled : $eachStudent->disabled);
                $mainStudent->save();

                if (!$participant){
                    $mainStudentDetail->nationality = ($mainStudentDetail->nationality ? $mainStudentDetail->nationality : $eachStudent->nationality);
                    if (!$mainStudentDetail->nationality) {
                        $mainStudentDetail->nationality = "";
                    }
    
                    $mainStudentDetail->birthdate = ($mainStudentDetail->birthdate ? $mainStudentDetail->birthdate : $eachStudent->birthdate);
                    $mainStudentDetail->height = ($mainStudentDetail->height ? $mainStudentDetail->height : $eachStudent->height);
                    $mainStudentDetail->weight = ($mainStudentDetail->weight ? $mainStudentDetail->weight : $eachStudent->weight);
                    $mainStudentDetail->physical_date = ($mainStudentDetail->physical_date ? $mainStudentDetail->physical_date : $eachStudent->physical_date);
                    $mainStudentDetail->instrument = ($mainStudentDetail->instrument ? $mainStudentDetail->instrument : $eachStudent->instrument);
                    $mainStudentDetail->swimming_level = ($mainStudentDetail->swimming_level ? $mainStudentDetail->swimming_level : $eachStudent->swimming_level);
                    $mainStudentDetail->shirt_size_id = ($mainStudentDetail->shirt_size_id ? $mainStudentDetail->shirt_size_id : $eachStudent->shirt_size_id);
                    $mainStudentDetail->classroom_teacher = ($mainStudentDetail->classroom_teacher ? $mainStudentDetail->classroom_teacher : $eachStudent->classroom_teacher);
                    $mainStudentDetail->notes = ($mainStudentDetail->notes ? $mainStudentDetail->notes : $eachStudent->notes);
                    $mainStudentDetail->save();
                }

                if (isset($eachStudent->PrimaryAddress)) {
                    $mainStudentPrimaryAddress->address_type_id = ($mainStudentPrimaryAddress->address_type_id ? $mainStudentPrimaryAddress->address_type_id : $eachStudent->PrimaryAddress->address_type_id);
                    $mainStudentPrimaryAddress->address = ($mainStudentPrimaryAddress->address ? $mainStudentPrimaryAddress->address : $eachStudent->PrimaryAddress->address);
                    $mainStudentPrimaryAddress->address2 = ($mainStudentPrimaryAddress->address2 ? $mainStudentPrimaryAddress->address2 : $eachStudent->PrimaryAddress->address2);
                    $mainStudentPrimaryAddress->city = ($mainStudentPrimaryAddress->city ? $mainStudentPrimaryAddress->city : $eachStudent->PrimaryAddress->city);
                    $mainStudentPrimaryAddress->state = ($mainStudentPrimaryAddress->state ? $mainStudentPrimaryAddress->state : $eachStudent->PrimaryAddress->state);
                    $mainStudentPrimaryAddress->zip = ($mainStudentPrimaryAddress->zip ? $mainStudentPrimaryAddress->zip : $eachStudent->PrimaryAddress->zip);
                    $mainStudentPrimaryAddress->save();
                }

                if (isset($eachStudent->Contacts)) {
                    foreach ($eachStudent->Contacts as $key => $eachContact) {

                        $eachContactType = (int)$eachContact->contact_type_id;
                        if ($key != ContactType::CODE_OTHERPHONE) {

                            if ($eachContactType) {
                                $studentContact->loadWhere("student_id = " . $mainStudent->id . " AND contact_type_id = $eachContactType ");

                                $studentContact->student_id = $mainStudent->id;
                                $studentContact->contact_type_id = $eachContactType;

                                if (!$studentContact->value && $eachContact->value) {
                                    $studentContact->value = ($studentContact->value ? $studentContact->value : $eachContact->value);
                                    if (!$studentContact->value) {
                                        $studentContact->value = "";
                                    }

                                    $studentContact->purpose = ($studentContact->purpose ? $studentContact->purpose : $eachContact->purpose);
                                    $studentContact->primary_contact = (int)($studentContact->primary_contact ? $studentContact->primary_contact : $eachContact->primary_contact);
                                    $studentContact->save();
                                }
                                $studentContact->clear();
                            }
                        } else {
                            $studentContact->student_id = $mainStudent->id;
                            $studentContact->contact_type_id = $eachContactType;

                            if ($eachContact->value) {
                                $studentContact->value = $eachContact->value;
                                if (!$studentContact->value) {
                                    $studentContact->value = "";
                                }

                                $studentContact->purpose = ($studentContact->purpose ? $studentContact->purpose : $eachContact->purpose);
                                $studentContact->primary_contact = (int)($studentContact->primary_contact ? $studentContact->primary_contact : $eachContact->primary_contact);
                                $studentContact->save();
                            }

                            $studentContact->clear();
                        }
                    }
                }

                if (isset($eachStudent->PickupPersons)) {
                    foreach ($eachStudent->PickupPersons as $eachPickup) {
                        $studentPickup->loadWhere("student_id = " . $mainStudent->id);
                        $studentPickup->student_id = $mainStudent->id;

                        $studentPickup->first_name = ($studentPickup->first_name ? $studentPickup->first_name : $eachPickup->first_name);
                        $studentPickup->last_name = ($studentPickup->last_name ? $studentPickup->last_name : $eachPickup->last_name);
                        $studentPickup->relation = ($studentPickup->relation ? $studentPickup->relation : $eachPickup->relation);

                        $studentPickup->save();
                        $studentPickup->clear();
                    }
                }
            }

            //soft-delete students
            Students::deleteStudents($studentIds);
        }
    }

    function saveFamilyPayerSharingInfo($familyAccountId, $payers)
    {
        if ($payers) {
            $familyPayer = new FamilyAccountPayer();
            foreach ($payers as $payerId => $data) {
                $familyPayer->loadWhere("family_account_id = $familyAccountId AND payer_id = $payerId");

                if (isset($data['share_payment_history'])) {
                    $familyPayer->share_payment_history = (int)$data['share_payment_history'];
                } else {
                    $familyPayer->share_payment_history = 0;
                }
                $familyPayer->save();
                $familyPayer->clear();
            }
        }
    }

    function saveFamilyPayerSharePaymentCOA($familyAccountId, $payersshare)
    {
        if ($familyAccountId) {
            $familyPayer = new FamilyAccountPayer();
            foreach ($payersshare as $key => $data) {
                $familyPayer->loadWhere("family_account_id = $familyAccountId AND payer_id = $key ");
                foreach ($data as $key1 => $data1) {
                    if ($key1 == 'sph') {
                        $familyPayer->share_payment_history = $data1;
                    }
                    if ($key1 == 'scoa') {
                        $familyPayer->share_coa = $data1;
                    }
                }
                $familyPayer->save();
                $familyPayer->clear();
            }
        }
    }

    function mergePayers($payerIds, $mainPayerId, $option)
    {
        $params = array("match" => $payerIds);
        $payers = Payers::getPayersInfo($params);
        $mainPayer = null;

        $admissibleMainPayers = array();
        $admissibleSubPayers = array();
        $inadmissiblePayers = array();

        $payer_status = array(); // 1 = main payer, 0 = sub payer
        if (count($payers)) {
            foreach ($payers as $eachPayer) {
                // merging and selection non main payer as main payer is allowed

                if ($eachPayer->id == $mainPayerId) { // && $payer_status[$eachPayer->id] == 1
                    $mainPayer = $eachPayer;
                } else {
                    //check if payer is the main family payer or sub payer
                    if (isset($eachPayer->FamilyAccounts) && count($eachPayer->FamilyAccounts)) {

                        $family = current($eachPayer->FamilyAccounts);

                        if ($family) {
                            if ($family->main_payer_id == $eachPayer->id) {
                                //main family payer
                                $admissibleMainPayers[] = $eachPayer;
                            } else {
                                $admissibleSubPayers[] = $eachPayer;
                            }
                        }
                    }
                }

            }
        }

        $connection = DbCon::instance()->getConnection();
        $family_account_id = 0;

        try {
            $connection->beginTransaction();

            if ($mainPayer && (count($admissibleMainPayers) || count($admissibleSubPayers))) {
                // if ($option == "add" || $option == 'delete'){
                //add each payer to family account of main payer
                if (isset($mainPayer->FamilyAccounts) && count($mainPayer->FamilyAccounts)) {
                    $mainFamily = current($mainPayer->FamilyAccounts);
                    $family_account_id = $mainFamily->id;

                    $fa = new FamilyAccount();
                    $fa->load($family_account_id);
                    $fa->main_payer_id = $mainPayer->id;
                    $fa->save();

                    if ($mainFamily) {
                        $admissible = array_merge($admissibleMainPayers, $admissibleSubPayers);

                        if (count($admissible)) {
                            $tempFamily = new FamilyAccount();
                            $tempFamPayer = new FamilyAccountPayer();
                            $tempPayer = new Payer();
                            foreach ($admissible as $eachMainPayer) {
                                //add each payer in the family to the family of the current merge main payer

                                if (isset($eachMainPayer->FamilyAccounts) && count($eachMainPayer->FamilyAccounts)) {
                                    $eachMainFamily = current($eachMainPayer->FamilyAccounts);

                                    if ($eachMainFamily) {
                                        //change the family association for each payer
                                        FamilyAccountsUtility::mergeFamilies($eachMainFamily->id, $mainFamily->id);

                                        //delete old family (if family_account_id != MainFamily id)
                                        if ($eachMainFamily->id != $mainFamily->id) {
                                            $tempFamily->clear();
                                            $tempFamily->load($eachMainFamily->id);

                                            if ($tempFamily->id) {
                                                $tempFamily->deleted = 1;
                                                $tempFamily->save();

                                                // change transaction to new family account
                                                AccountingClassJournals::updateClientId($tempFamily->id, $mainFamily->id);
                                            }
                                        }else{//bug 69599
                                            $tempPayer->clear();
                                            $tempPayer->loadWhere("id=" . $mainPayerId);

                                            $tempFamily->clear();
                                            $tempFamily->loadWhere("id=" . $mainFamily->id);
                                            $tempFamily->account_name = $tempPayer->last_name .', '. $tempPayer->first_name;
                                            $tempFamily->save();
                                        }

                                        // TODO need testing here
                                        if ($option == "delete") { // amount due and coa from deleted payer assigned to the kept payer
                                            $tempFamPayer->clear();
                                            $tempFamPayer->loadWhere("family_account_id=" . $eachMainFamily->id);
                                            // sek di delete family acocunt kudune yo pindah neng family account sek anyar
                                            if ($tempFamPayer->id) {
                                                $tempFamPayer->family_account_id = $mainFamily->id;
                                                $tempFamPayer->deleted = 1;
                                                $tempFamPayer->save();
                                            }

                                            $tempPayer->clear();
                                            $tempPayer->load($eachMainPayer->id);

                                            if ($tempPayer->id) {

                                                // if payer still have due, assign the due to the kept payer
                                                $query = new QueryCreator();
                                                $query->addSelect("aci.id AS invoice_id,aci.journal_id,dues.registration_id,dues.amountdue");
                                                $query->addFrom(AccountingClassInvoice::TABLE_NAME . " AS aci");
                                                $query->addJoin("(
                                                            SELECT
                                                                invoices.invoice_id,
                                                                invoices.registration_id,
                                                                invoices.amount - IFNULL(payments.amount, 0) - IFNULL(adjusts.adjust_amount, 0) AS amountdue
                                                            FROM
                                                                (
                                                                    SELECT
                                                                        s_acii.invoice_id,
                                                                        sum(s_acii.amount) AS amount,
                                                                        s_acir.registration_id,
                                                                        s_r.family_account_id
                                                                    FROM
                                                                        accounting_class_invoice_items AS s_acii
                                                                    JOIN accounting_class_invoices AS s_aci ON s_aci.id = s_acii.invoice_id
                                                                    JOIN accounting_class_journals AS s_acj ON s_acj.id = s_aci.journal_id
                                                                    JOIN accounting_class_invoice_registrations AS s_acir ON s_acir.invoice_id=s_aci.id
                                                                    JOIN registrations AS s_r ON s_r.id = s_acii.registration_id
                                                                    WHERE
                                                                        s_acj.cancelled_on = '0000-00-00 00:00:00'
                                                                    AND s_acii.cancelled_on = '0000-00-00 00:00:00'
                                                                    AND s_r.family_account_id = " . $eachMainFamily->id . "
                                                                    AND s_r.deleted=0
                                                                    AND s_r.cancelled_on = '0000-00-00 00:00:00'
                                                                    GROUP BY
                                                                        s_acii.invoice_id
                                                                ) AS invoices
                                                            LEFT JOIN (
                                                                SELECT
                                                                    s_acii.invoice_id,
                                                                    s_acii.registration_id,
                                                                    s_acp.payer_id,
                                                                    sum(s_acpii.amount) AS amount
                                                                FROM
                                                                    accounting_class_invoice_items AS s_acii
                                                                LEFT JOIN accounting_class_payment_invoice_items AS s_acpii ON s_acpii.invoice_item_id = s_acii.id
                                                                LEFT JOIN accounting_class_payments AS s_acp ON s_acp.id = s_acpii.payment_id
                                                                LEFT JOIN accounting_class_journals AS s_acj ON s_acj.id = s_acp.journal_id
                                                                WHERE
                                                                    s_acj.cancelled_on = '0000-00-00 00:00:00'
                                                                GROUP BY
                                                                    s_acii.invoice_id
                                                            ) AS payments ON payments.invoice_id = invoices.invoice_id

                                                            LEFT JOIN (
                                                                SELECT
                                                                    aci.id AS invoice_id,
                                                                    sum(accai.amount) AS adjust_amount
                                                                FROM
                                                                    accounting_class_journals acj
                                                                LEFT JOIN accounting_class_credit_adjustments acca ON acj.id = acca.journal_id
                                                                LEFT JOIN accounting_class_credit_adjustment_items accai ON acca.id = accai.credit_adjustment_id
                                                                LEFT JOIN accounting_class_invoices aci ON aci.id = acca.invoice_id
                                                                WHERE
                                                                    acj.transaction_type = 3
                                                                AND posted = 1
                                                                GROUP BY
                                                                    aci.id
                                                            ) AS adjusts ON adjusts.invoice_id = invoices.invoice_id
                                                        )", 'dues', 'invoice_id', 'aci', 'id');
                                                $query->addWhere("amountdue IS NOT NULL");
                                                $query->addGroupBy("aci.id");

                                                $db = DBCon::instance();
                                                $results = $db->executeQuery($query->createSQL());
                                                // assign invoice

                                                $r = new Registration();

                                                foreach ($results as $key_ => $val_) {
                                                    $r->clear();
                                                    $r->load($val_['registration_id']);
                                                    $r->payer_id = $mainPayer->id;

                                                    $r->save();
                                                }


                                                //all registrations
                                                $rs = new Registrations();
                                                $rs->clear();
                                                $rs->loadWhere("payer_id=" . $tempPayer->id);

                                                if (count($rs->toArray())) {

                                                    $regtemp = array();
                                                    $regtemp = $rs->toArray();

                                                    foreach ($regtemp as $reg) {
                                                        $eachReg = new Registration();
                                                        $eachReg->clear();
                                                        $eachReg->loadWhere("id=" . $reg['id']);
                                                        $eachReg->payer_id = $mainPayer->id;
                                                        $eachReg->family_account_id = $mainFamily->id;
                                                        $eachReg->save();
                                                    }
                                                }

                                                // need to change payer_id too for the following:
                                                // --- START ---
                                                $accas = new AccountingClassCreditAdjustments();
                                                $accas->clear();
                                                $accas->loadWhere("payer_id=" . $tempPayer->id);
                                                if (count($accas->toArray())) {
                                                    foreach ($accas as $acca) {
                                                        $acca->payer_id = $mainPayer->id;
                                                        $acca->save();
                                                    }
                                                }

                                                $accoas = new AccountingClassCreditOnAccounts();
                                                $accoas->clear();
                                                $accoas->loadWhere("payer_id=" . $tempPayer->id);
                                                if (count($accoas->toArray())) {
                                                    foreach ($accoas as $accoa) {
                                                        $accoa->payer_id = $mainPayer->id;
                                                        $accoa->save();
                                                    }
                                                }

                                                $acis = new AccountingClassInvoices();
                                                $acis->clear();
                                                $acis->loadWhere("payer_id=" . $tempPayer->id);
                                                if (count($acis->toArray())) {
                                                    foreach ($acis as $aci) {
                                                        $aci->payer_id = $mainPayer->id;
                                                        $aci->save();
                                                    }
                                                }

                                                $acps = new AccountingClassPayments();
                                                $acps->clear();
                                                $acps->loadWhere("payer_id=" . $tempPayer->id);
                                                if (count($acps->toArray())) {
                                                    foreach ($acps as $acp) {
                                                        $acp->payer_id = $mainPayer->id;
                                                        $acp->save();
                                                    }
                                                }

                                                $acpscs = new AccountingClassPaymentServiceCharges();
                                                $acpscs->clear();
                                                $acpscs->loadWhere("payer_id=" . $tempPayer->id);
                                                if (count($acpscs->toArray())) {
                                                    foreach ($acpscs as $acpsc) {
                                                        $acpsc->payer_id = $mainPayer->id;
                                                        $acpsc->save();
                                                    }
                                                }

                                                $acrs = new AccountingClassRefunds();
                                                $acrs->clear();
                                                $acrs->loadWhere("payer_id=" . $tempPayer->id);
                                                if (count($acrs->toArray())) {
                                                    foreach ($acrs as $acr) {
                                                        $acr->payer_id = $mainPayer->id;
                                                        $acr->save();
                                                    }
                                                }
                                                // --- END ---

                                                $tempPayer->deleted = 1;

                                                $tempPayer->save();

                                                // credit card ?
                                                $pc = new PayerCards();
                                                $results = PayerCards::getPayerCardIdByPayerId($tempPayer->id);

                                                if (count($results) > 0) {
                                                    foreach ($results as $k => $val) {
                                                        $pc->clear();
                                                        $pc->loadById($val['id']);
                                                        $pc->deleted = 1;
                                                        $pc->save();
                                                    }
                                                }
                                            }

                                        }

                                    }
                                }
                            }
                        }


                    }
                }
            }

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            $logger = new BaseLogger();
            $logger->logInfo('ERROR : merge accounts :' . $e->getMessage() . $e->getTraceAsString());
        }

        return $family_account_id;
    }

    function mergeFamilies($oldFamilyId, $newFamilyId)
    {
        FamilyAccountPayers::changeFamilyAssociation($oldFamilyId, $newFamilyId);
        FamilyAccountStudents::changeFamilyAssociation($oldFamilyId, $newFamilyId);
        FamilyAccountGuardians::changeFamilyAssociation($oldFamilyId, $newFamilyId);
        Registrations::changeFamilyAssociation($oldFamilyId, $newFamilyId);
    }

    public function getPayerNamesArray($familyAccountId, $format = "%first_name% %last_name%")
    {
        $payers = Payers::getUniquePayersByFamilyId($familyAccountId);

        $payerNames = array();
        foreach ($payers as $payer) {
            $payer = (object)$payer;
            $payer->first_name = ucfirst($payer->first_name);
            $payer->middle_name = ucfirst($payer->middle_name);
            $payer->last_name = ucfirst($payer->last_name);
            $payerNames[$payer->id] = Utility::format($payer, $format);
        }
        return $payerNames;
    }

    public function getAgeInMonth($p_strDate)
    {
        list($BirthYear, $BirthMonth, $BirthDay) = explode("-", $p_strDate);
        $YearDiff = date("Y") - $BirthYear;
        $MonthDiff = date("m") - $BirthMonth;
        $DayDiff = date("d") - $BirthDay;
        // If the birthday has not occured this year
        if ($DayDiff < 0 || $MonthDiff < 0) {
            $YearDiff--;
            if ($DayDiff < 0)
                $MonthDiff--;
            $ageMonth = ($YearDiff * 12) + (12 - $BirthMonth) + ($BirthMonth + $MonthDiff);
        } else $ageMonth = ($YearDiff * 12) + $MonthDiff;
        return $ageMonth;
    }

    public function getAgeInMonthAsOfDate($p_strDate, $in_strDate)
    {
        list($BirthYear, $BirthMonth, $BirthDay) = explode("-", $p_strDate);
        list($inBirthYear, $inBirthMonth, $inBirthDay) = explode("-", $in_strDate);
        $YearDiff = $inBirthYear - $BirthYear;
        $MonthDiff = $inBirthMonth - $BirthMonth;
        $DayDiff = $inBirthDay - $BirthDay;
        // If the birthday has not occured this year
        if ($DayDiff < 0 || $MonthDiff < 0) {
            $YearDiff--;
            if ($DayDiff < 0)
                $MonthDiff--;
            $ageMonth = ($YearDiff * 12) + (12 - $BirthMonth) + ($BirthMonth + $MonthDiff);
        } else $ageMonth = ($YearDiff * 12) + $MonthDiff;
        return $ageMonth;
    }

    public function checkusedcc($payercardid)
    {
        $query = new QueryCreator();
        $query->addSelect('count(acpc.payer_card_id) as usedcc');
        $query->addFrom(AccountingClassPaymentsCard::TABLE_NAME . ' as acpc');
        $query->addWhere('acpc.payer_card_id = ' . $payercardid);
        $db = DBCon::instance();
        $rslt = $db->executeQuery($query->createSQL());
        if ($rslt[0]['usedcc'] > 0)
            $used = true;
        else
            $used = false;
        return $used;
    }

    public function checksetupreccc($payercardid)
    {
        $db = DBCon::instance();
        $query = new QueryCreator();
        $query->addSelect("acprs.id AS recurringSetupID ");
        $query->addFrom(AccountingClassPaymentsRecurringSetup::TABLE_NAME . " AS acprs ");
        $query->addJoin("LEFT JOIN " . AccountingClassPaymentsRecurring::TABLE_NAME . " AS acpr ON acprs.id = acpr.recurring_setup_id ");
        $query->addWhere("acprs.payer_card_id = " . $payercardid);
        $query->addWhere("acpr.payment_date IS NULL ");
        $query->addWhere("acprs.deleted = 0");
        $sqlQuery = $query->createSQL();
        $rslt = $db->executeQuery($sqlQuery);
        if (count($rslt) > 0)
            $used = true;
        else
            $used = false;
        return $used;
    }

    public function deleteCC($payercardid)
    {
        $payerCreditCard = new PayerCreditCard();
        if ($payercardid) {
            $payerCreditCard->loadById($payercardid);
            $payerCreditCard->enabled = 0;
            $payerCreditCard->deleted = 1;
            $payerCreditCard->save();
        }
    }

    public function getPayerShareAccount($family_account_id, $code = NULL)
    {
        $auth = BaseAuth::getInstance();
        $payerlist = array();
        if ($auth->id) {
            $payer = new Payer(true);
            $payer->loadWhere("user_id = " . $auth->id);
            $payer = $payer->toArray();
            $payerlist[] = $payer['id'];

            $fap1 = new FamilyAccountPayers();
            if ($code == 1)
                $fap1->loadWhere("family_account_id = " . $family_account_id . " AND share_payment_history = 1 AND deleted = 0");
            elseif ($code == 2)
                $fap1->loadWhere("family_account_id = " . $family_account_id . " AND share_coa = 1 AND deleted = 0");
            else
                $fap1->loadWhere("family_account_id = " . $family_account_id . " AND deleted = 0");
            $fap1 = $fap1->toArray();
            foreach ($fap1 as $item) {
                if ($item['payer_id'] != $payer['id']) {
                    $payerlist[] = $item['payer_id'];
                }
            }
        }
        return $payerlist;
    }

    public function getEmailsFamAccount($fam_account_id, $emailof)
    {
        $query = new QueryCreator();
        $query->addSelect("fa.id");
        $query->addFrom(FamilyAccount::TABLE_NAME . " AS fa");

        if ($emailof == 'guardian') {
            $query->addSelect("g.id, " .
                "g.first_name, " .
                "g.last_name, " .
                "gc.value AS email");
            $query->addJoin("LEFT JOIN " . FamilyAccountGuardian::TABLE_NAME . " AS fag ON fa.id=fag.family_account_id");
            $query->addJoin("LEFT JOIN " . Guardian::TABLE_NAME . " AS g ON fag.guardian_id=g.id");
            $query->addJoin("LEFT JOIN " . GuardianContact::TABLE_NAME . " AS gc ON g.id=gc.guardian_id AND gc.contact_type_id=11");
            $query->addWhere("fag.deleted=0");
            $query->addWhere("g.deleted=0");
        }

        if ($emailof == 'payer') {
            $query->addSelect("p.id, " .
                "p.first_name, " .
                "p.last_name, " .
                "pc.value AS email");
            $query->addJoin("LEFT JOIN " . FamilyAccountPayer::TABLE_NAME . " AS fap ON fa.id=fap.family_account_id");
            $query->addJoin("LEFT JOIN " . Payer::TABLE_NAME . " AS p ON fap.payer_id=p.id");
            $query->addJoin("LEFT JOIN " . PayerContact::TABLE_NAME . " AS pc ON p.id=pc.payer_id AND pc.contact_type_id=11");
            $query->addWhere("fap.deleted=0");
            $query->addWhere("p.deleted=0");
        }

        $query->addWhere("fa.id=$fam_account_id");
        $query->addWhere("fa.deleted=0");
        $db = DBCon::instance();
        $qryResults = $db->executeQuery($query->createSQL());

        $result = array();

        foreach ($qryResults as $row) {
            if (!empty($row['email'])) {
                $result[$row['id']]['name'] = $row['first_name'] . " " . $row['last_name'];
                $result[$row['id']]['email'] = $row['email'];
            }
        }

        return $result;
    }

    public function saveEmailSent()
    {
        $needed_data = new Zend_Session_Namespace('FOR_STORING_EMAILS');

        $cuser_id = Utility_AuthUtility::getCurrentUserId();

        $is_all_payerids_zero = false;
        $payer_ids_zero_temp = array();
        $uploadFiles = array();
        $recipientList = array();

        if (isset($needed_data->attachments) && $needed_data->attachments) {
            if (is_array($needed_data->attachments)) {
                foreach ($needed_data->attachments as $attachment) {
                    $uploadFiles[] = array(
                        "uploadFilename" => $attachment->filename,
                        "description" => "Email Attachment",
                        "originalFilename" => $attachment->filename);
                }
            } else {
                $uploadFiles[] = array(
                    "uploadFilename" => $needed_data->attachments->filename,
                    "description" => "Email Attachment",
                    "originalFilename" => $needed_data->attachments->filename);
            }

            $needed_data->attachments = null;
        }

        if (isset($needed_data->payer_ids) && $needed_data->payer_ids) {
            foreach ($needed_data->payer_ids as $payer_id) {
                if ($payer_id) {
                    $recipientList[] = array(
                        "recipient_id" => $payer_id,
                        "recipient_type" => $needed_data->recipient_type ? $needed_data->recipient_type : "payer",
                        "received_as" => "to");
                } else {
                    $payer_ids_zero_temp[] = $payer_id;
                }
            }

            if (count($payer_ids_zero_temp) == count($needed_data->payer_ids))
                $is_all_payerids_zero = true;

            $needed_data->payer_ids = null;
        }

        // for guardians
        if (isset($needed_data->guardian_ids) && $needed_data->guardian_ids) {
            foreach ($needed_data->guardian_ids as $guardian_id) {
                if ($guardian_id) {
                    $recipientList[] = array(
                        "recipient_id" => $guardian_id,
                        "recipient_type" => "guardian",
                        "received_as" => "to");
                }
            }

            $needed_data->guardian_ids = null;
        }

        // we also need to save email sent to student - if email is sent from CMS report
        if (isset($needed_data->student_ids) && $needed_data->student_ids) {
            foreach ($needed_data->student_ids as $student_id) {
                if ($student_id) {
                    $recipientList[] = array(
                        "recipient_id" => $student_id,
                        "recipient_type" => "student",
                        "received_as" => "to");
                }
            }

            $needed_data->student_ids = null;
        }

        if (!$is_all_payerids_zero) {
            $email = new FamilyAccountEmail();
            $email->date_sent = date("Y-m-d H:i:s");
            $email->subject = $needed_data->emailInfo->subject;
            $email->body = $needed_data->emailInfo->body;
            $email->family_account_id = (int)$needed_data->family_account_id;
            $email->save();

            $uploadedFile = new UploadedFile();
            $attachment = new FamilyAccountEmailAttachment();
            foreach ($uploadFiles as $file) {
                $uploadedFile->clear();
                $uploadedFile->filename = $file["uploadFilename"];
                $uploadedFile->description = $file["description"];
                $uploadedFile->uploaded_on = date("Y-m-d H:i:s");
                $uploadedFile->uploaded_by_id = $cuser_id;
                $uploadedFile->save();

                $attachment->clear();
                $attachment->family_account_email_id = $email->id;
                $attachment->uploaded_file_id = $uploadedFile->id;
                $attachment->file_name = $file["originalFilename"];
                $attachment->save();
            }

            $recipient = new FamilyAccountEmailRecipient();
            foreach ($recipientList as $rec) {
                $recipient->clear();
                $recipient->family_account_email_id = $email->id;
                $recipient->recipient_type = $rec["recipient_type"];
                $recipient->recipient_id = $rec["recipient_id"];
                $recipient->received_as = $rec["received_as"];
                $recipient->save();
            }
        }

    }

    public function getFamilyAccountEmails($fam_account_id)
    {
        $result = array();

        $query = new QueryCreator();
        $query->addSelect("fae.id, fae.family_account_id, fae.date_sent, fae.subject," .
            "faer.recipient_id, faer.recipient_type");
        $query->addFrom(FamilyAccountEmail::TABLE_NAME . " AS fae");
        $query->addJoin("LEFT JOIN " . FamilyAccountEmailRecipient::TABLE_NAME . " AS faer ON fae.id=faer.family_account_email_id");
        $query->addWhere("fae.family_account_id=$fam_account_id");
        $query->addWhere("faer.received_as='to'");
        $query->addGroupBy("fae.id, fae.subject, fae.body");
        $query->addOrderBy("fae.date_sent ASC");
        $db = DBCon::instance();
        $qryResults = $db->executeQuery($query->createSQL());

        foreach ($qryResults as $row) {
            if (empty($result[$row['id']])) {
                $result[$row['id']]['family_account_id'] = $row['family_account_id'];
                $result[$row['id']]['date_sent'] = date("m/d/Y", strtotime($row['date_sent']));
                $result[$row['id']]['subject'] = $row['subject'];
            }

            if ($row['recipient_type'] == "payer") {
                $payer = new Payer();
                $payer->loadWhere("id=" . $row['recipient_id']);
                $result[$row['id']]['to'][] = $payer->first_name . " " . $payer->last_name;
            } elseif ($row['recipient_type'] == "guardian") {
                $guardian = new Guardian();
                $guardian->loadWhere("id=" . $row['recipient_id']);
                $result[$row['id']]['to'][] = $guardian->first_name . " " . $guardian->last_name;
            } elseif ($row['recipient_type'] == "student") {
                $student = new Student();
                $student->loadWhere("id=" . $row['recipient_id']);
                $result[$row['id']]['to'][] = $student->first_name . " " . $student->last_name;
            }
        }

        return $result;
    }

    // save ecfe eform

    public function getFamilyAccountEmailDetails($emailid)
    {
        $result = array();

        $query = new QueryCreator();
        $query->addSelect("fae.id, fae.date_sent, fae.subject, fae.body, " .
            "faer.recipient_id, faer.recipient_type");
        $query->addFrom(FamilyAccountEmail::TABLE_NAME . " AS fae");
        $query->addJoin("LEFT JOIN " . FamilyAccountEmailRecipient::TABLE_NAME . " AS faer ON fae.id=faer.family_account_email_id");
        $query->addWhere("fae.id=$emailid");
        $db = DBCon::instance();
        $qryResults = $db->executeQuery($query->createSQL());

        $emails_string = "";

        foreach ($qryResults as $row) {
            if (empty($result)) {
                $result['id'] = $row['id'];
                $result['subject'] = $row['subject'];
                $result['date_sent'] = date("m/d/Y", strtotime($row['date_sent']));

                $substr_server = 'http://' . $_SERVER['HTTP_HOST'];
                $html_str = str_replace($substr_server, '', $row['body']);
                $result['body'] = htmlspecialchars_decode($html_str);
            }

            if ($row['recipient_type'] == "payer") {
                $payer = new Payer();
                $payer->loadWhere("id=" . $row['recipient_id']);
                $result['to'][$row['recipient_id']]['id'] = $row['recipient_id'];
                $result['to'][$row['recipient_id']]['name'] = $payer->first_name . " " . $payer->last_name;
                $result['to'][$row['recipient_id']]['type'] = $row['recipient_type'];

                $payerEmail = PayerContacts::getContacts(array($row['recipient_id']), array('EMAIL'));
                $result['to'][$row['recipient_id']]['email'] = $payerEmail[0]['value'];
                $emails_string .= $payerEmail[0]['value'] . "; ";
            } elseif ($row['recipient_type'] == "guardian") {
                $guardian = new Guardian();
                $guardian->loadWhere("id=" . $row['recipient_id']);
                $result['to'][$row['recipient_id']]['id'] = $row['recipient_id'];
                $result['to'][$row['recipient_id']]['name'] = $guardian->first_name . " " . $guardian->last_name;
                $result['to'][$row['recipient_id']]['type'] = $row['recipient_type'];

                $guardianEmail = GuardianContacts::getContacts(array($row['recipient_id']), array('EMAIL'));
                $result['to'][$row['recipient_id']]['email'] = $guardianEmail[0]['value'];
                $emails_string .= $guardianEmail[0]['value'] . "; ";
            } elseif ($row['recipient_type'] == "student") {
                $student = new Student();
                $student->loadWhere("id=" . $row['recipient_id']);
                $result['to'][$row['recipient_id']]['id'] = $row['recipient_id'];
                $result['to'][$row['recipient_id']]['name'] = $student->first_name . " " . $student->last_name;
                $result['to'][$row['recipient_id']]['type'] = $row['recipient_type'];

                $studentEmail = StudentContacts::getContacts2(array($row['recipient_id']), array('EMAIL'));
                $result['to'][$row['recipient_id']]['email'] = $studentEmail[0]['value'];
                $emails_string .= $studentEmail[0]['value'] . "; ";
            }
        }

        $attachments = new FamilyAccountEmailAttachments();
        $attachments->loadWhere("family_account_email_id=" . $result['id']);

        foreach ($attachments as $attachment) {
            $result['attachment'][$attachment->id]['uploaded_file_id'] = $attachment->uploaded_file_id;
            $result['attachment'][$attachment->id]['file_name'] = $attachment->file_name;
        }

        return $result;
    }

    /*
     * Save to Three-Step Customer Vault Payline
     */

    public function deleteCCnew($ccid, $enabled = 1, $isOrg, $config = null)
    {
        $pc = new PayerCard();

        try {
            if (!$config->payment->gateway_enabled) {
                $pc->clear();
                $pc->loadWhere("id = " . $ccid);
                if ($pc->id) {
                    $pc->deleted = 1;
                    $pc->enabled = $enabled;
                    if($isOrg){
                    $pc->deleted_by = Utility_AuthUtility::getCurrentUserId();
                    $pc->deleted_on = Utility_CommonUtility::getCurrentDateTime();
                    }
                    $pc->save();
                }
            } else {
                if ($config->payment->payment_gateway == 'payline') {
                    // remove from customer vault!
                    if ($enabled == 0) {
                        FamilyAccountsUtility::deleteCustomerFromCustomerVault($ccid, $enabled, $isOrg, $config);
                    } elseif ($enabled == 1) {
                        $pc->clear();
                        $pc->loadWhere("id = " . $ccid);
                        if ($pc->id) {
                            $pc->deleted = 1;
                            $pc->enabled = $enabled;
                            if($isOrg){
                            $pc->deleted_by = Utility_AuthUtility::getCurrentUserId();
                            $pc->deleted_on = Utility_CommonUtility::getCurrentDateTime();
                            }
                            $pc->save();
                        }
                    }
                } else {
                    $pc->clear();
                    $pc->loadWhere("id = " . $ccid);
                    if ($pc->id) {
                        $pc->deleted = 1;
                        $pc->enabled = $enabled;
                        if($isOrg){
                        $pc->deleted_by = Utility_AuthUtility::getCurrentUserId();
                        $pc->deleted_on = Utility_CommonUtility::getCurrentDateTime();
                        }
                        $pc->save();
                    }
                }
            }
            return true;

        } catch (Exception $e) {
            $logger = new BaseLogger();
            $logger->logError($e);
            return false;
        }
    }

    public function deleteCustomerFromCustomerVault($payercard_id, $enabled, $isOrg, $config)
    {
        // get customer vault id
        $customer_vault_id = "";
        $pc = new PayerCard();
        $pc->load($payercard_id);
        if ($pc->customer_vault_id) {
            $customer_vault_id = $pc->customer_vault_id;
            $customer = new Payline;
            $customer->APIKey = $config->payline->key;
            $customer->transactionType = 'delete-customer';
            $customer->vaultId = $customer_vault_id;

            if ($customer->doCustomerStep1()) {
                try {
                    $pc->clear();
                    $pc->loadWhere("id = " . $payercard_id);
                    if ($pc->id) {
                        $pc->deleted = 1;
                        $pc->enabled = $enabled;
                        if($isOrg){
                        $pc->deleted_by = Utility_AuthUtility::getCurrentUserId();
                        $pc->deleted_on = Utility_CommonUtility::getCurrentDateTime();
                        }
                        $pc->save();
                    }
                    return true;
                } catch (Exception $e) {
                    $logger = new BaseLogger();
                    $logger->logError($e);
                    return false;
                }
            } else {
                return false;
            }
        } else {
            try {
                $pc->clear();
                $pc->loadWhere("id = " . $payercard_id);
                if ($pc->id) {
                    $pc->deleted = 1;
                    $pc->enabled = $enabled;
                    if($isOrg){
                    $pc->deleted_by = Utility_AuthUtility::getCurrentUserId();
                    $pc->deleted_on = Utility_CommonUtility::getCurrentDateTime();
                    }
                    $pc->save();
                }
                return true;
            } catch (Exception $e) {
                $logger = new BaseLogger();
                $logger->logError($e);
                return false;
            }
        }

    }

    public function saveEcfeEformsData($data, $id = null, $from_editstudent = 0)
    {

        $ecfeeform = new EcfeEform();
        if ($id) {
            $ecfeeform->load($id);
            $ecfeeform_id = $id;
        }

        $cuser_id = Utility_AuthUtility::getCurrentUserId();

        if ($data) {
            if (!$from_editstudent) {
                $ecfeeform->form_id = $data->id;
                $ecfeeform->registration_id = $data->registration_id;
                $ecfeeform->family_account_id = $data->family_account_id;
                if (isset($data->student_id) && $data->student_id)
                    $ecfeeform->student_id = $data->student_id;
                else $ecfeeform->student_id = 0;
                $ecfeeform->submitted_by_id = $cuser_id;
                $ecfeeform->updated_on = date("Y-m-d H:i:s");
                $ecfeeform->save();
            } else {
                $ecfeeform->updated_on = date("Y-m-d H:i:s");
                $ecfeeform->updated_by = $cuser_id;
                $ecfeeform->save();
            }
            $ecfeeform_id = $ecfeeform->id;

            foreach ($data->items as $dataitem) {
                if ($dataitem->upload_file && !empty($data->uploaded_files['uploadFilename'])) { // for immunization file upload
                    $uploadedFile = new UploadedFile();
                    $uploadedFile->clear();
                    $uploadedFile->filename = $data->uploaded_files['uploadFilename'];
                    $uploadedFile->description = $data->uploaded_files["description"];
                    $uploadedFile->uploaded_on = date("Y-m-d H:i:s");
                    $uploadedFile->uploaded_by_id = $cuser_id;
                    $uploadedFile->save();
                }
                $ecfeformdata = new EcfeEformsData();
                if ($dataitem->ecfeformdataid) {
                    $ecfeformdata->load($dataitem->ecfeformdataid);
                }
                $ecfeformdata->form_element_id = $dataitem->id;
                $ecfeformdata->ecfe_form_id = $ecfeeform->id;
                $ecfeformdata->name = $dataitem->name;
                // modified here so it will not replace blank value those with already uploaded immunization file
                if ($dataitem->form_element_type_id == 4 && !$dataitem->upload_file)
                    $ecfeformdata->value = "";
                else {
                    if (!empty($dataitem->value))
                        $ecfeformdata->value = $dataitem->value;
                }

                if ($dataitem->upload_file && $uploadedFile->id) { // for immunization file upload
                    $ecfeformdata->value = "uploaded_file_id-" . $uploadedFile->id;
                }
                $ecfeformdata->display_order = $dataitem->display_order;
                $ecfeformdata->show_in_subject = $dataitem->show_in_subject;
                $ecfeformdata->save();
            }
        }
        return $ecfeeform_id;
    }

    function savePayerImport($params)
    {
        $now = date("Y-m-d H:i:s", time());
        $params['now'] = $now;

        $u = new User();
        if ($params['username']) {
            $u->username = $params['username'];
        } else {
            $u->username = 'auto_';
        }
        if ($params['password'])
            $u->password = Utility_AuthUtility::hashPassword($params['password']);
        $u->user_type_id = UserUtility::USER_TYPE_PAYERS; //4
        $u->app_access = FamilyAccountsUtility::getDefaultPayerAppAccess(); //20
        $u->deleted = 0;
        $u->active = 1;
        $u->created_by_id = 1;
        $u->created_on = $now;
        $u->save();
        if ($u->username == 'auto_') {
            $temp_unid = $u->id;
            $u->clear();
            $u->load($temp_unid);
            $u->username = 'auto_' . $temp_unid;
            $u->save();
        }

        $prms = array("fields" => array("id"), "parentSetting" => "CR_PUBLIC");
        $permissions = ApplicationSettings::getApplicationSettings($prms);
        foreach ($permissions as $permission)
            UserSettingPermission::saveRecord(true, $u->id, $permission["id"]);

        $p = new Payer();
        $p->first_name = $params['first_name'];
        $p->middle_name = $params['middle_name'];
        $p->last_name = $params['last_name'];
        $p->price_tier_id = $params['price_tier'];
        $p->user_id = $u->id;
        $p->deleted = 0;
        $p->created_on = $now;
        $p->created_by_id = Utility_AuthUtility::getCurrentUserId();
        $p->save();

        $params['payer_id'] = $p->id;
        $addressTypesArray = AddressTypes::getAddressTypesArray();
        $pa = new PayerAddress();
        $pa->payer_id = $p->id;
        $pa->address_type_id = $addressTypesArray[AddressType::CODE_HOME];
        $pa->primary_address = 1;
        $pa->address = $params['address'];
        $pa->address2 = $params['address2'];
        $pa->city = $params['city'];
        $pa->state = $params['state'];
        $pa->zip = $params['zip'];
        $pa->save();

        $contactTypesArray = ContactTypes::getContactTypesArray();
        $pci = new PayerContact();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE]; //3
        $pci->value = $params['day_phone'];
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE_EXT]; //4 - 3 digit
        $pci->value = '';
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE]; //5
        $pci->value = $params['night_phone'];
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE_EXT]; //6 - 3 digit
        $pci->value = '';
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_HOMEPHONE]; //7
        $pci->value = $params['home_phone'];
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE]; //8
        $pci->value = $params['work_phone'];
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE_EXT]; //9 - 3 digit
        $pci->value = '';
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_MOBILE]; //10
        $pci->value = $params['cell_phone'];
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_EMAIL]; //11
        $pci->value = $params['email'];
        $pci->primary_contact = 0;
        $pci->save();

        $pci->clear();
        $pci->payer_id = $p->id;
        $pci->contact_type_id = $contactTypesArray[ContactType::CODE_FAX]; //12
        $pci->value = $params['fax'];
        $pci->primary_contact = 0;
        $pci->save();

        $fa = new FamilyAccount();
        $fa->account_name = $params['last_name'] . ", " . $params['first_name'];
        $fa->main_payer_id = $p->id;
        $fa->updated_on = $now;
        $fa->updated_by_id = Utility_AuthUtility::getCurrentUserId();
        $fa->created_on = $now;
        $fa->created_by_id = Utility_AuthUtility::getCurrentUserId();
        $fa->created_from = 'admin';
        $fa->save();

        $params['family_account_id'] = $fa->id;
        $fap = new FamilyAccountPayer();
        $fap->family_account_id = $fa->id;
        $fap->payer_id = $p->id;
        $fap->save();

        $fan = new FamilyAccountNote();
        $fan->family_account_id = $fa->id;
        $fan->notes = $params['comments'];
        $fan->created_on = $now;
        $fan->save();

        FamilyAccountsUtility::saveStudentImport($params);

        FamilyAccountsUtility::createIdNumber();

        if ($params['add_as_guardian'])
            FamilyAccountsUtility::saveGuardianImport($params);

    }

    function saveStudentImport($params)
    {
        $s = new Student();
        $s->first_name = $params['first_name'];
        $s->middle_name = $params['middle_name'];
        $s->last_name = $params['last_name'];
        if (strtolower($params['gender']) == 'male')
            $s->gender = 1;
        if (strtolower($params['gender']) == 'female')
            $s->gender = 2;
        $s->age_group_id = $params['age'];
        $s->price_tier_id = $params['price_tier'];
        $s->enabled = 1;
        $s->deleted = 0;
        $s->payer_id = $params['payer_id'];
        $s->created_on = $params['now'];
        $s->created_by_id = Utility_AuthUtility::getCurrentUserId();;
        $s->save();

        $addressTypesArray = AddressTypes::getAddressTypesArray();
        $sa = new StudentAddress();
        $sa->student_id = $s->id;
        $sa->address_type_id = $addressTypesArray[AddressType::CODE_HOME];
        $sa->primary_address = 1;
        $sa->address = $params['address'];
        $sa->address2 = $params['address2'];
        $sa->city = $params['city'];
        $sa->state = $params['state'];
        $sa->zip = $params['zip'];
        $sa->save();

        $contactTypesArray = ContactTypes::getContactTypesArray();

        $sci = new StudentContactInfo();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE]; //3
        $sci->value = $params['day_phone'];
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE_EXT]; //4 - 3 digit
        $sci->value = '';
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE]; //5
        $sci->value = $params['night_phone'];
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE_EXT]; //6 - 3 digit
        $sci->value = '';
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_HOMEPHONE]; //7
        $sci->value = $params['home_phone'];
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE]; //8
        $sci->value = $params['work_phone'];
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE_EXT]; //9 - 3 digit
        $sci->value = '';
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_MOBILE]; //10
        $sci->value = $params['cell_phone'];
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_EMAIL]; //11
        $sci->value = $params['email'];
        $sci->primary_contact = 0;
        $sci->save();

        $sci->clear();
        $sci->student_id = $s->id;
        $sci->contact_type_id = $contactTypesArray[ContactType::CODE_FAX]; //12
        $sci->value = $params['fax'];
        $sci->primary_contact = 0;
        $sci->save();

        $sag = new FamilyAccountStudent();
        $sag->family_account_id = $params['family_account_id'];
        $sag->student_id = $s->id;
        $sag->save();

        //CUSTOM CLIENT
        $sd = new StudentDetail();
        $sd->student_id = $s->id;
        $bd = split('/', $params['birth_date']);
        $sd->birthdate = $bd[2] . "-" . str_pad($bd[0], 2, "0", STR_PAD_LEFT) . "-" . str_pad($bd[1], 2, "0", STR_PAD_LEFT);
        $sd->save();

    }

    public function createIdNumber()
    {
        $query = new QueryCreator();
        $query->addSelect("RPAD(SUBSTRING(alphanum(LOWER(TRIM(replace(p.last_name,' ','')))), 1, 4),4,0) as kode_digit,p.id,p.last_name,p.first_name,p.middle_name,p.age_group_id,fap.family_account_id");
        $query->addFrom("payers AS p");
        $query->addJoin("LEFT JOIN family_account_payers as fap ON fap.payer_id = p.id");
        $query->addwhere("p.id_no IS NULL");
        $db = DBCon::instance();
        $temp_query_result = $db->executeQuery($query->createSQL());

        foreach ($temp_query_result as $key => $value) {
            $kode = '';
            $id = $value[id];
            $search = $value[kode_digit];
            $query = "SELECT COUNT(*) AS jumlah FROM (SELECT p.id_no FROM payers AS p WHERE p.id_no LIKE '" . $search . "%' UNION SELECT s.id_no FROM students AS s  WHERE s.id_no LIKE '" . $search . "%') AS t1";
            $temp_query_check_result = $db->executeQuery($query);

            if ($temp_query_check_result[0][jumlah] > 0) {
                $count = $temp_query_check_result[0][jumlah] + 1;
                $kode = $value[kode_digit] . str_pad($count, 5, "0", STR_PAD_LEFT);
            } else {
                $kode = $value[kode_digit] . "00001";
            }

            $sqlQuery = "UPDATE payers SET id_no = '" . $kode . "' WHERE id = " . $id;
            $db->executeCommand($sqlQuery);

            $query = new QueryCreator();
            $query->addSelect("s.id,s.last_name,s.first_name,s.middle_name,s.age_group_id,fas.family_account_id");
            $query->addFrom("students AS s");
            $query->addJoin("LEFT JOIN family_account_students as fas ON fas.student_id = s.id");
            $query->addwhere("s.id_no IS NULL");
            $query->addwhere("fas.family_account_id ='" . addslashes($value[family_account_id]) . "'");
            $query->addwhere("s.first_name ='" . addslashes($value[first_name]) . "'");
            $query->addwhere("s.last_name ='" . addslashes($value[last_name]) . "'");
            if ($value[age_group_id])
                $query->addwhere("s.age_group_id ='" . $value[age_group_id] . "'");
            $db = DBCon::instance();
            $temp_query_find_result = $db->executeQuery($query->createSQL());

            if (!empty($temp_query_find_result)) {
                $sqlQuery = "UPDATE students SET id_no = '" . $kode . "' WHERE id = " . $temp_query_find_result[0][id];
                $db->executeCommand($sqlQuery);
            }
        }

        $query = new QueryCreator();
        $query->addSelect("RPAD(SUBSTRING(alphanum(LOWER(TRIM(replace(s.last_name,' ','')))), 1, 4),4,0) as kode_digit,id");
        $query->addFrom(Student::TABLE_NAME . " AS s");
        $query->addwhere("s.id_no IS NULL");
        $db = DBCon::instance();
        $temp_query_result = $db->executeQuery($query->createSQL());

        foreach ($temp_query_result as $key => $value) {
            $kode = '';
            $id = $value[id];
            $search = $value[kode_digit];
            $query = "SELECT COUNT(*) AS jumlah FROM (SELECT p.id_no FROM payers AS p WHERE p.id_no LIKE '" . $search . "%' UNION SELECT s.id_no FROM students AS s  WHERE s.id_no LIKE '" . $search . "%') AS t1";
            $temp_query_check_result = $db->executeQuery($query);

            if ($temp_query_check_result[0][jumlah] > 0) {
                $count = $temp_query_check_result[0][jumlah] + 1;
                $kode = $value[kode_digit] . str_pad($count, 5, "0", STR_PAD_LEFT);
            } else {
                $kode = $value[kode_digit] . "00001";
            }

            $sqlQuery = "UPDATE " . Student::TABLE_NAME . " SET id_no = '" . $kode . "' WHERE id = " . $id;
            $db->executeCommand($sqlQuery);
        }
    }

    function saveGuardianImport($params)
    {
        $g = new Guardian();
        $g->first_name = $params['first_name'];
        $g->middle_name = $params['middle_name'];
        $g->last_name = $params['last_name'];
        $g->enabled = 1;
        $g->deleted = 0;
        $g->payer_id = $params['payer_id'];
        $g->created_on = $params['now'];
        $g->created_by_id = Utility_AuthUtility::getCurrentUserId();;
        $g->save();

        $addressTypesArray = AddressTypes::getAddressTypesArray();
        $ga = new GuardianAddress();
        $ga->guardian_id = $g->id;
        $ga->address_type_id = $addressTypesArray[AddressType::CODE_HOME];
        $ga->primary_address = 1;
        $ga->address = $params['address'];
        $ga->address2 = $params['address2'];
        $ga->city = $params['city'];
        $ga->state = $params['state'];
        $ga->zip = $params['zip'];
        $ga->save();

        $contactTypesArray = ContactTypes::getContactTypesArray();

        $gci = new GuardianContact();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE]; //3
        $gci->value = $params['day_phone'];
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_DAYPHONE_EXT]; //4 - 3 digit  
        $gci->value = '';
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE]; //5
        $gci->value = $params['night_phone'];
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_NIGHTPHONE_EXT]; //6 - 3 digit  
        $gci->value = '';
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_HOMEPHONE]; //7
        $gci->value = $params['home_phone'];
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE]; //8
        $gci->value = $params['work_phone'];
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_OFFICEPHONE_EXT]; //9 - 3 digit
        $gci->value = '';
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_MOBILE]; //10
        $gci->value = $params['cell_phone'];
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_EMAIL]; //11
        $gci->value = $params['email'];
        $gci->primary_contact = 0;
        $gci->save();

        $gci->clear();
        $gci->guardian_id = $g->id;
        $gci->contact_type_id = $contactTypesArray[ContactType::CODE_FAX]; //12
        $gci->value = $params['fax'];
        $gci->primary_contact = 0;
        $gci->save();

        $fag = new FamilyAccountGuardian();
        $fag->family_account_id = $params['family_account_id'];
        $fag->guardian_id = $g->id;
        $fag->save();
    }

    function saveFamilyAccountInfoOrganization($familyAccountInfo, $payerInfo, $public = 1)
    {
        $currentUserId = Utility_AuthUtility::getCurrentUserId();
        $familyAccount = new FamilyAccount();
        $accountId = (int)$familyAccountInfo['id'];
        if ($accountId) {
            $familyAccount->load($accountId);
        }

        if (isset($familyAccountInfo['account_name'])) {
            $familyAccount->account_name = $familyAccountInfo['account_name'];
        } else {
            $familyAccount->account_name = '';
        }

        $familyAccount->updated_by_id = ($currentUserId) ? $currentUserId : NULL;
        $familyAccount->created_by_id = ($currentUserId) ? $currentUserId : NULL;
        $familyAccount->created_from = $public ? "public" : "admin";
        $familyAccount->is_organization = 1;
        $familyAccount->save();

        if ($familyAccount->id) {
            /*
             * Save Family Account custom fields
             * */
            $orgProfile = new OrgProfileFormInfos();
            $orgProfile->saveOrgProfileInfos($familyAccount->id, $familyAccountInfo['custom_fields'], $familyAccountInfo['sessionID']);
        }

        if (count($payerInfo) > 0) {
            $payerData = new Zend_Session_Namespace('ORG_PAYER_DATA');
            $result = FamilyAccountsUtility::savePayerForFamilyAccountOrganization($familyAccount->id, $payerInfo);
            $payerData->id = $result['payer_id'];
        }

        return $familyAccount->id;
    }
    
    function saveCCForFamilyAccountOrganization($family_account_id, $creditcard)
    {
        $result = false;
        if ($creditcard) {
            $client_session = new Zend_Session_Namespace(SESSION_CLIENT);
            $config = $client_session->config;
            if (!$config->payment->gateway_enabled) {
                $user = new User();
                $userId = (int)$creditcard['user_id'];
                $user->load($userId);
                $creditcard['sec_key'] = $user->sec_key;
                $result = FamilyAccountsUtility::saveCreditCardInfo2($creditcard,1);
            } else {
                if ($config->payment->payment_gateway == 'payline') {
                    // save to customer vault
                    $result = FamilyAccountsUtility::saveToCustomerVaultPayline($creditcard, $client_session->config);
                    // get card id
                    $payline_card = new Zend_Session_Namespace('PAYLINE_CARD');
                    $result = $payline_card->card_id;
                } else {
                    $user = new User();
                    $userId = (int)$creditcard['user_id'];
                    $user->load($userId);
                    $creditcard['sec_key'] = $user->sec_key;
                    $result = FamilyAccountsUtility::saveCreditCardInfo2($creditcard,1);
                }
            }
        }
        return $result;
    }

    function savePayerForFamilyAccountOrganization($familyAccountId, $payerInfo)
    {
        $logger = new BaseLogger();
        $result = array();

        $currentUserId = Utility_AuthUtility::getCurrentUserId();
        $userChanged = false;

        $payerUser = $payerInfo['users'];
        $user = new User();
        $userId = (int)$payerUser['user_id'];
        if ($userId > 0) {
            $user->load($userId);
            if ($payerUser['username'] == '') {
                $user->username = 'auto_' . $userId;
                $user->password = Utility_AuthUtility::hashPassword($userId);
                $userChanged = true;
            }
            if ($payerUser['username'] != '' && $payerUser['username'] != $user->username) {
                $user->username = $payerUser['username'];
                $userChanged = true;
            }
            if ($payerUser['password']) {
                $user->password = Utility_AuthUtility::hashPassword($payerUser['password']);
                $userChanged = true;
            }
        } else {
            if ($payerUser['username'] != '')
                $user->username = $payerUser['username'];
            else {
                $payerObj = new Payers();
                $autoid = (int)$payerObj->getAutoId() + 1;
                $autousername = 'auto_' . $autoid . '_' . md5(microtime()) . '_' . rand();
                $user->username = $autousername;
            }

            if ($payerUser['password'])
                $user->password = Utility_AuthUtility::hashPassword($payerUser['password']);
            else
                $user->password = Utility_AuthUtility::hashPassword($payerUser['autopassword']);

            $user->sec_key = Utility_AuthUtility::hashPassword($user->password);

            if ($user->username != NULL)
                $userChanged = true;
        }
        $payerInfo['sec_key'] = $user->sec_key;
        $payerInfo['payer_cards']['sec_key'] = $user->sec_key;
        
        if ($userChanged) {
            $defaultAppAccess = FamilyAccountsUtility::getDefaultPayerAppAccess();
            $user->app_access = $user->app_access ? $user->app_access : $defaultAppAccess;

            $user->user_type_id = UserUtility::USER_TYPE_PAYERS;
            $user->created_by_id = ($currentUserId) ? $currentUserId : NULL;

            $logger->logInfo(json_encode($user));
            $user->save();

            $payerUser['user_id'] = $user->id;
            //save permission for current user
            $params = array("fields" => array("id"), "parentSetting" => "CR_PUBLIC");
            $permissions = ApplicationSettings::getApplicationSettings($params);
            $usp = new UserSettingPermission();
            foreach ($permissions as $permission) {
                $usp->saveRecord(true, $user->id, $permission["id"]);
            }
        }

        $payer = new Payer();
        $payerDetail = $payerInfo['payers'];
        if ($payerDetail['id']) {
            $payer->load($payerDetail['id']);
        }
        $payer->first_name = $payerDetail['first_name'];
        $payer->last_name = $payerDetail['last_name'];

        if ($user->id) {
            $payer->user_id = (int)$user->id;

            $payerData = new Zend_Session_Namespace('ORG_PAYER_DATA');
            $payerData->user_id = (int)$user->id;

            if (isset($payerUser['active'])) {
                $user->active = $payerUser['active'];
                $user->save();
            }
        }

        $payer->created_by_id = ($currentUserId) ? $currentUserId : NULL;

        $logger->logInfo(json_encode($payer));
        $payer->save();

        $familyPayer = new FamilyAccountPayer();
        $familyPayer->loadWhere("family_account_id = " . $familyAccountId . " AND payer_id = " . $payer->id);
        $familyPayer->family_account_id = $familyAccountId;
        $familyPayer->payer_id = $payer->id;

        $logger->logInfo(json_encode($familyPayer));
        $familyPayer->save();

        $familyAccount = new FamilyAccount();
        $familyAccount->load($familyAccountId);
        //set payer as main payer
        $familyAccount->main_payer_id = $payer->id;
        $familyAccount->save();

        $payerAddresses = $payerInfo['payer_addresses'];
        $payerAddress = new PayerAddress();
        $payerAddressId = (int)$payerAddresses['address_id'];
        if ($payerAddressId) {
            $payerAddress->load($payerAddressId);
        } else {
            $payerAddress->loadWhere("payer_id = " . $payer->id . " AND primary_address = 1 ");
        }

        $payerAddress->address_type_id = 1;
        $payerAddress->primary_address = 1;
        $payerAddress->address = $payerAddresses['address'];
        $payerAddress->address2 = $payerAddresses['address2'];
        $payerAddress->city = $payerAddresses['city'];
        $payerAddress->state = $payerAddresses['state'];
        $payerAddress->zip = ($payerAddresses['zip']) ? $payerAddresses['zip'] : 0;
        $payerAddress->foreign_zip = $payerAddresses['foreign_zip'];
        $payerAddress->country = $payerAddresses['country'];

        $payerAddress->payer_id = $payer->id;

        $logger->logInfo(json_encode($payerInfo));
        $payerAddress->save();

        $ct = new ContactTypes();

        $payerContacInfos = $payerInfo['contact_infos'];
        if (isset($payerContacInfos) && is_array($payerContacInfos) && count($payerContacInfos) > 0) {
            //add contacts
            $contactTypesArray = $ct->getContactTypesArray();
            $payerContact = new PayerContact();

            foreach ($payerContacInfos as $contact => $value) {
                $contactTypeId = $contactTypesArray["$contact"];
                if ($contactTypeId) {
                    $payerContact->loadWhere("payer_id = $payer->id AND contact_type_id = $contactTypeId ");
                    $payerContact->contact_type_id = $contactTypeId;
                    $payerContact->payer_id = $payer->id;
                    $payerContact->value = $value;

                    $logger->logInfo(json_encode($payerContact));
                    $payerContact->save();
                    $payerContact->clear();
                }
            }

        }

        $payerCards = $payerInfo['payer_cards'];
        if (count($payerCards) > 0 && $payerCards['card_number'] != "" && $payerCards['card_type_id'] > 0) {
            // save credit card info
            // check MA first
            $client_session = new Zend_Session_Namespace(SESSION_CLIENT);
            $config = $client_session->config;
            if (!$config->payment->gateway_enabled) {
                $payerCards['payer_id'] = $payer->id;
                $payerCards['user_id'] = $user->id;
                $result['payer_card_id'] = FamilyAccountsUtility::saveCreditCardInfo2($payerCards, 1);
            } else {
                if ($config->payment->payment_gateway == 'payline') {
                    // validate
                    if (trim($payerCards['card_number']) != "" && $payerCards['card_type'] > 0) {

                        if (preg_match("/(XXXX-XXXX-XXXX)/i", $payerCards['card_number'])) {
                            // continue
                        } else {
                            $creditCardType = new CreditCardType();
                            $creditCardType->load($payerCards['card_type']);
                            $creditname = $creditCardType->value;
                            $validation = $this->validatecc($creditname, $payerCards['card_number']);

                            if (!$validation) {
                                throw new Exception('The number of the credit card is not valid.');
                            }
                        }
                    }

                    // save to customer vault
                    $payerCards['payer_id'] = $payer->id;
                    $payerCards['action'] = 'add-customer';
                    $payerCards['card_type'] = $payerCards['card_type_id'];
                    $payerCards['user_id'] = $user->id;

                    if ($payerCards['id'] > 0) {
                        $payerCreditCard = new PayerCreditCard();
                        $payerCreditCard->loadWhere("id=" . $payerCards['id']);
                        if ($payerCreditCard->customer_vault_id != NULL || $payerCreditCard->customer_vault_id != "") {
                            $payerCards['action'] = 'update-customer';
                            $payerCards['customer_vault_id'] = $payerCreditCard->customer_vault_id;
                        }
                    }

                    FamilyAccountsUtility::saveToCustomerVaultPayline($payerCards, $config);

                    $payline_card = new Zend_Session_Namespace('PAYLINE_CARD');
                    if ($payline_card->card_id > 0) $result['payer_card_id'] = $payline_card->card_id;
                } else {
                    $payerCards['payer_id'] = $payer->id;
                    $payerCards['user_id'] = $user->id;
                    $result['payer_card_id'] = FamilyAccountsUtility::saveCreditCardInfo2($payerCards, 1);
                }
            }
        }

        /*
         * save custom fields
         * */
        if ($payer->id > 0) {
            $orgProfile = new OrgContactFormInfos();
            $orgProfile->saveOrgContactInfos($payer->id, $payerInfo['custom_fields'], $payerInfo['sessionID']);
        }

        $result['payer_id'] = $payer->id;

        return $result;
    }

    public function saveCreditCardInfo2($cardData, $public = 0)
    {
        if ($cardData) {
            $payerCreditCard = new PayerCreditCard();
            if ($cardData['id']) {
                $payerCreditCard->loadWhere("id=" . $cardData['id']);
                $flag = 1;
            }
            $payerCreditCard->payer_id = (isset($cardData['payer_id'])) ? $cardData['payer_id'] : $cardData['payer_id_old'];
            $payerCreditCard->first_name = isset($cardData['first_name']) ? $cardData['first_name'] : " ";
            $payerCreditCard->last_name = isset($cardData['last_name']) ? $cardData['last_name'] : " ";
            $cardNo = str_replace('-', '', $cardData['card_number']);
            $payerCreditCard->card_number = substr($cardNo, -4);
            $payerCreditCard->card_type_id = (isset($cardData['card_type'])) ? $cardData['card_type'] : $cardData['card_type_old'];
            $payerCreditCard->back_number = null;//Bug 2010, set to null. We don't store this kind of information
            $payerCreditCard->share_cc = isset($cardData['share_cc']) ? $cardData['share_cc'] : ($cardData['share_cc_old'] ? $cardData['share_cc_old'] : 0);
            $payerCreditCard->enabled = isset($cardData['enabled']) && $cardData['enabled'] > 0 ? 1 : 0;
            $expiration_month = (isset($cardData['card_expiration_month'])) ? $cardData['card_expiration_month'] : $cardData['card_expiration_month_old'];
            $expiration_year = (isset($cardData['card_expiration_year'])) ? $cardData['card_expiration_year'] : $cardData['card_expiration_year_old'];
            $payerCreditCard->expiration = TimeUtility::convertToSQLDateFormat(date("m/d/Y", mktime(0, 0, 0, $expiration_month, 1, $expiration_year)));
            $payerCreditCard->address = $cardData['address'];
            $payerCreditCard->address2 = $cardData['address2'];
            $payerCreditCard->city = $cardData['city'];
            $payerCreditCard->state = (isset($cardData['state'])) ? $cardData['state'] : $cardData['state_old'];
            $payerCreditCard->zip = $cardData['zip'];
            $payerCreditCard->email = $cardData['email'];
            /*
             * Adding New Fields for ORG ACCT
             * */
            $payerCreditCard->foreign_zip = $cardData['foreign_zip'];
            $payerCreditCard->country = $cardData['country'];
            if (isset($cardData['company_name'])) {
                $payerCreditCard->company_name = $cardData['company_name'];
            }
            // add delete
            if (isset($cardData['deleted'])) {
                $payerCreditCard->deleted = $cardData['deleted'];
            }

            if (isset($cardData['name_on_card'])) {
                $payerCreditCard->name_on_card = $cardData['name_on_card'];
            }

            $payerCreditCard->save();
            if ($payerCreditCard->id) {
                $payerCardData = new PayerCardData();
                $payerCardData->saveCardData2($flag, $payerCreditCard->id, $cardData['user_id'], $cardData['card_number'], $cardData['sec_key']);
            }

            if ($public == 1) {
                return $payerCreditCard->id;
            }
        }
    }

    function saveStudentForFamilyAccountOrganization($familyAccountId, $studentData, $sessionID = "")
    {
        $currentUserId = Utility_AuthUtility::getCurrentUserId();

        $studentInfo = $studentData['students'];

        $student = new Student();
        $studentId = (int)$studentInfo['id'];
        if ($studentId) {
            $student->load($studentId);
        } else {
            $student->created_on = date('Y-m-d');
        }

        $student->first_name = $studentInfo['first_name'];
        $student->last_name = $studentInfo['last_name'];
        $student->created_by_id = $currentUserId;

        $student->save();

        $familyStudent = new FamilyAccountStudent();
        $familyStudent->clear();
        $familyStudent->loadWhere("family_account_id = " . $familyAccountId . " AND student_id = " . $student->id);
        $familyStudent->family_account_id = $familyAccountId;
        $familyStudent->student_id = $student->id;
        $familyStudent->save();

        $studentInfoAddress = $studentData['student_addresses'];

        //student address
        $studentAddress = new StudentAddress();
        $studentAddress->loadWhere("student_id = " . $student->id . " AND primary_address = 1");

        if (isset($studentInfoAddress['address_type_id']) && $studentInfoAddress['address_type_id']) {
            $studentAddress->address_type_id = $studentInfoAddress['address_type_id'];
        }

        if (isset($studentInfoAddress['main_address']) && $studentInfoAddress['main_address']) {
            $studentAddress->primary_address = $studentInfoAddress['main_address'];
        }

        if (isset($studentInfoAddress['address']) && $studentInfoAddress['address']) {
            $studentAddress->address = $studentInfoAddress['address'];
        }

        if (isset($studentInfoAddress['address2']) && $studentInfoAddress['address2']) {
            $studentAddress->address2 = $studentInfoAddress['address2'];
        }

        if (isset($studentInfoAddress['city']) && $studentInfoAddress['city']) {
            $studentAddress->city = $studentInfoAddress['city'];
        }

        if (isset($studentInfoAddress['state']) && $studentInfoAddress['state']) {
            $studentAddress->state = $studentInfoAddress['state'];
        }

        if (isset($studentInfoAddress['zip'])) {
            $studentAddress->zip = $studentInfoAddress['zip'];
        }
        if (isset($studentInfoAddress['foreign_zip'])) {
            $studentAddress->foreign_zip = $studentInfoAddress['foreign_zip'];
        }
        if (isset($studentInfoAddress['country'])) {
            $studentAddress->country = $studentInfoAddress['country'];
        }
        $studentAddress->student_id = $student->id;
        $studentAddress->primary_address = 1;
        $studentAddress->save();

        $studentInfoContact = $studentData['student_contact_infos'];
        if (isset($studentInfoContact) && is_array($studentInfoContact)) {
            //add contacts
            $ct = new ContactTypes();
            $contactTypesArray = $ct->getContactTypesArray();
            $studentContact = new StudentContactInfo();
            foreach ($studentInfoContact as $contact => $value) {
                $contactTypeId = $contactTypesArray["$contact"];
                $studentContact->loadWhere("student_id = " . $student->id . " AND contact_type_id = $contactTypeId ");
                $studentContact->contact_type_id = $contactTypeId;
                $studentContact->student_id = $student->id;
                $studentContact->value = (is_null($value)) ? "" : $value;
                $studentContact->save();
                $studentContact->clear();
            }
        }

        if ($student->id) {
            $studentInfoCustom = $studentData['custom_fields'];
            /*
             * Save Students custom fields
             * */
            $orgProfile = new OrgParticipantFormInfos();
            $orgProfile->saveOrgParticipantInfos($student->id, $studentInfoCustom, $sessionID);
        }

        FamilyAccountsUtility::createIdNumber();

        return $student->id;
    }

    public function validatecc($cardname, $cardnumber, $from = "") {

        $cards = array(
            array('name' => 'American Express', 'length' => '15', 'prefixes' => '34,37', 'checkdigit' => true),
            array('name' => 'Carte Blanche', 'length' => '14', 'prefixes' => '300,301,302,303,304,305,36,38', 'checkdigit' => true),
            array('name' => 'Diners Club', 'length' => '14', 'prefixes' => '300,301,302,303,304,305,36,38', ' checkdigit' => true),
            array('name' => 'Discover', 'length' => '16', 'prefixes' => '6011', ' checkdigit' => true),
            array('name' => 'Enroute', 'length' => '15', 'prefixes' => '2014,2149', 'checkdigit' => true),
            array('name' => 'JCB', 'length' => '15,16', 'prefixes' => '3,1800,2131', 'checkdigit' => true),
            array('name' => 'Maestro', 'length' => '16', 'prefixes' => '5020,6', 'checkdigit' => true),
            array('name' => 'MasterCard', 'length' => '16', 'prefixes' => '51,52,53,54,55', 'checkdigit' => true),
            array('name' => 'Solo', 'length' => '16,18,19', 'prefixes' => '6334, 6767', 'checkdigit' => true),
            array('name' => 'Switch', 'length' => '16,18,19', 'prefixes' => '4903,4905,4911,4936,564182,633110,6333,6759', 'checkdigit' => true),
            array('name' => 'VISA', 'length' => '13,16', 'prefixes' => '4', 'checkdigit' => true),
            array('name' => 'Visa Electron', 'length' => '16', 'prefixes' => '417500,4917,4913', 'checkdigit' => true)
        );

        $ccErrorNo = 0;
        $ccErrors [0] = "Unknown card type";
        $ccErrors [1] = "No card number provided";
        $ccErrors [2] = "Credit card number has invalid format";

        if ($from == "editpayer") {
            $ccErrors [3] = "The number of the credit card is not valid.";
            $ccErrors [4] = "The number of the credit card is not valid.";
        } else {
            $ccErrors [3] = "The card number is not valid.";
            $ccErrors [4] = "The card number is not valid.";
        }

        $ccErrors [5] = "This card has already expired";

        $validcc = true;
        $errortext = "";

        /*
         * See if this card's date is valid
         * if(strlen($exp_month)==1) $exp_month='0'.$exp_month;
         * if(strlen($exp_year)==2) $exp_year='20'.$exp_year;
         * $formMonth=$exp_year;
         * $formMonth.=$exp_month;
         * $today=getdate();
         * $currentMonth=date('Ym',$today[0]);
         * if( $formMonth <= $currentMonth)
         * {
         * $errornumber = 5;
         * $errortext = $ccErrors [$errornumber];
         * return false;
         * }
         * */

        // Establish card type
        $cardType = -1;
        for ($i = 0; $i < sizeof($cards); $i++) {
            // See if it is this card (ignoring the case of the string)
            if (strtolower(trim($cardname)) == strtolower(trim($cards[$i]['name']))) {
                $cardType = $i;
                break;
            }
        }

        // If card type not found, report an error
        if ($validcc && $cardType == -1) {
            $errornumber = 0;
            $errortext = $ccErrors [$errornumber];
            $validcc = false;
        }
        // Ensure that the user has provided a credit card number
        if ($validcc && strlen($cardnumber) == 0) {
            $errornumber = 1;
            $errortext = $ccErrors [$errornumber];
            $validcc = false;
        }
        // Remove any spaces from the credit card number
        $cardNo = str_replace(' ', '', $cardnumber);
        $cardNo = str_replace('-', '', $cardnumber);

        // See if the length is valid for this card
        if ($validcc) {
            $LengthValid = false;
            $lengths = split(',', $cards[$cardType]['length']);
            for ($j = 0; $j < sizeof($lengths); $j++) {
                if (strlen($cardNo) == $lengths[$j]) {
                    $LengthValid = true;
                    break;
                }
            }
            // See if all is OK by seeing if the length was valid.
            if (!$LengthValid) {
                $errornumber = 4;
                $errortext = $ccErrors [$errornumber];
                $validcc = false;
            }
        }

        // Check that the number is numeric and of the right sort of length.
        if ($validcc && !eregi('^[0-9]{13,19}$', $cardNo)) {
            $errornumber = 2;
            $errortext = $ccErrors [$errornumber];
            $validcc = false;
        }
        // Now check the modulus 10 check digit - if required
        if ($validcc && $cards[$cardType]['checkdigit']) {
            $checksum = 0;                                  // running checksum total
            $mychar = "";                                   // next char to process
            $j = 1;                                         // takes value of 1 or 2
            // Process each digit one by one starting at the right
            for ($i = strlen($cardNo) - 1; $i >= 0; $i--) {
                // Extract the next digit and multiply by 1 or 2 on alternative digits.
                $calc = $cardNo{$i} * $j;
                // If the result is in two digits add 1 to the checksum total
                if ($calc > 9) {
                    $checksum = $checksum + 1;
                    $calc = $calc - 10;
                }
                // Add the units element to the checksum total
                $checksum = $checksum + $calc;
                // Switch the value of j
                if ($j == 1) {
                    $j = 2;
                } else {
                    $j = 1;
                };
            }
            // All done - if checksum is divisible by 10, it is a valid modulus 10.
            // If not, report an error.
            if ($checksum % 10 != 0) {
                $errornumber = 3;
                $errortext = $ccErrors [$errornumber];
                $validcc = false;
            }
        }
        if ($validcc) {
            // The following are the card-specific checks we undertake.
            // Load an array with the valid prefixes for this card
            $prefix = split(',', $cards[$cardType]['prefixes']);
            // Now see if any of them match what we have in the card number
            $PrefixValid = false;
            for ($i = 0; $i < sizeof($prefix); $i++) {
                $exp = '^' . $prefix[$i];
                if (ereg($exp, $cardNo)) {
                    $PrefixValid = true;
                    break;
                }
            }
            // If it isn't a valid prefix there's no point at looking at the length
            if (!$PrefixValid) {
                $errornumber = 3;
                $errortext = $ccErrors [$errornumber];
                $validcc = false;
            }
        }

        $data = array();
        // $validcc = true;

        if (!$validcc) {
            $ccvalidation = new Zend_Session_Namespace('CC_VALIDATION');
            $ccvalidation->result = 1;
            $ccvalidation->message = $errortext;
        } else {
            $ccvalidation = new Zend_Session_Namespace('CC_VALIDATION');
            $ccvalidation->result = 0;
            $ccvalidation->message = "";
        }

        return $validcc;
    }
}


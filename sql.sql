
/******************************************************************************/
/***                                Triggers                                ***/
/******************************************************************************/
CREATE TRIGGER TAU_DUT_PRT_BITRIX FOR I_DUT_PRT
ACTIVE AFTER UPDATE POSITION 10
                        AS
DECLARE VARIABLE pID_REQ D_ID;
BEGIN
  IF((NEW.dprt_apr_dat IS DISTINCT FROM OLD.dprt_apr_dat)
  AND (NEW.dprt_apr_time IS DISTINCT FROM OLD.dprt_apr_time)
  AND (OLD.id_tpex=2)
  AND (NEW.dprt_num IS NOT NULL))
  THEN BEGIN
SELECT dut.id_req
FROM i_dut dut
WHERE dut.id_dut=OLD.id_dut
    INTO :pID_REQ;

UPDATE OR INSERT INTO s_bitrix_changes (id_req, id_dut, dprt_num)
VALUES (:pID_REQ, OLD.id_dut, NEW.dprt_num);
END
END



CREATE TRIGGER TAU_I_DUT_BITRIX FOR I_DUT
ACTIVE AFTER UPDATE POSITION 20
                        AS
BEGIN
  IF(NEW.dut_description IS DISTINCT FROM OLD.dut_description)
  THEN BEGIN
UPDATE OR INSERT INTO s_bitrix_changes (id_req, id_dut, dut_description)
VALUES (OLD.id_req, OLD.id_dut, NEW.dut_description);
END

  IF(NEW.dut_sernum IS DISTINCT FROM OLD.dut_sernum)
  THEN BEGIN
UPDATE OR INSERT INTO s_bitrix_changes (id_req, id_dut, dut_sernum)
VALUES (OLD.id_req, OLD.id_dut, NEW.dut_sernum);
END

  IF(NEW.dut_notes IS DISTINCT FROM OLD.dut_notes)
  THEN BEGIN
UPDATE OR INSERT INTO s_bitrix_changes (id_req, id_dut, dut_notes)
VALUES (OLD.id_req, OLD.id_dut, NEW.dut_notes);
END

  IF(NEW.dut_kit IS DISTINCT FROM OLD.dut_kit)
  THEN BEGIN
UPDATE OR INSERT INTO s_bitrix_changes (id_req, id_dut, dut_kit)
VALUES (OLD.id_req, OLD.id_dut, NEW.dut_kit);
END
END



CREATE TRIGGER TAU_I_REQUEST_BITRIX FOR I_REQUEST
ACTIVE AFTER UPDATE POSITION 0
                        AS
BEGIN
  IF(NEW.req_agrm IS DISTINCT FROM OLD.req_agrm)
  THEN BEGIN
UPDATE OR INSERT INTO s_bitrix_changes (id_req, req_agrm)
VALUES (OLD.id_req, NEW.req_agrm);
END
END



/******************************************************************************/
/***                                 Tables                                 ***/
/******************************************************************************/
CREATE TABLE S_BITRIX_CHANGES (
    ID_REQ           D_ID NOT NULL /* D_ID = INTEGER */,
    REQ_AGRM         D_VCHAR31 /* D_VCHAR31 = VARCHAR(31) */,
    ID_DUT           D_ID /* D_ID = INTEGER */,
    DUT_DESCRIPTION  D_VCHAR400 /* D_VCHAR400 = VARCHAR(400) */,
    DUT_SERNUM       D_VCHAR400 /* D_VCHAR400 = VARCHAR(400) */,
    DUT_NOTES        D_VCHAR400 /* D_VCHAR400 = VARCHAR(400) */,
    DUT_KIT          D_VCHAR400 /* D_VCHAR400 = VARCHAR(400) */,
    ID_TPEX          D_ID /* D_ID = INTEGER */,
    DPRT_NUM         D_VCHAR31 /* D_VCHAR31 = VARCHAR(31) */
);

/******************************************************************************/
/***                              Primary keys                              ***/
/******************************************************************************/
ALTER TABLE S_BITRIX_CHANGES ADD CONSTRAINT PK_S_BITRIX_CHANGES PRIMARY KEY (ID_REQ);

/******************************************************************************/
/***                          Fields descriptions                           ***/
/******************************************************************************/

COMMENT ON COLUMN S_BITRIX_CHANGES.ID_REQ IS
'ID заявки';

COMMENT ON COLUMN S_BITRIX_CHANGES.REQ_AGRM IS
'№ Договора (заявка)';

COMMENT ON COLUMN S_BITRIX_CHANGES.ID_DUT IS
'ID образца';

COMMENT ON COLUMN S_BITRIX_CHANGES.DUT_DESCRIPTION IS
'Описание (образца)';

COMMENT ON COLUMN S_BITRIX_CHANGES.DUT_SERNUM IS
'ID номер образца';

COMMENT ON COLUMN S_BITRIX_CHANGES.DUT_NOTES IS
'Примечания (образец, б24 - Производитель образца)';

COMMENT ON COLUMN S_BITRIX_CHANGES.DUT_KIT IS
'Комплект (образец)';

COMMENT ON COLUMN S_BITRIX_CHANGES.ID_TPEX IS
'ID вида работ';

COMMENT ON COLUMN S_BITRIX_CHANGES.DPRT_NUM IS
'Номер протокола';

/******************************************************************************/
/***                               Privileges                               ***/
/******************************************************************************/

/* Privileges of users */
GRANT ALL ON S_BITRIX_CHANGES TO UT_OWNER WITH GRANT OPTION;
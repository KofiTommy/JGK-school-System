-- House and Exeat module (additive schema only)

CREATE TABLE IF NOT EXISTS tblhouse (
    houseid VARCHAR(40) NOT NULL PRIMARY KEY,
    housename VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    datetimeentry DATETIME NOT NULL,
    recordedby VARCHAR(30) NOT NULL,
    UNIQUE KEY uq_housename (housename),
    INDEX idx_house_status (status)
);

CREATE TABLE IF NOT EXISTS tblhousemaster (
    assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
    houseid VARCHAR(40) NOT NULL,
    userid VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    datetimeentry DATETIME NOT NULL,
    recordedby VARCHAR(30) NOT NULL,
    INDEX idx_housemaster_teacher (userid),
    INDEX idx_housemaster_house (houseid),
    INDEX idx_housemaster_status (status)
);

CREATE TABLE IF NOT EXISTS tblstudenthouse (
    assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
    userid VARCHAR(30) NOT NULL,
    houseid VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    datetimeentry DATETIME NOT NULL,
    recordedby VARCHAR(30) NOT NULL,
    INDEX idx_studenthouse_user (userid),
    INDEX idx_studenthouse_house (houseid),
    INDEX idx_studenthouse_status (status)
);

CREATE TABLE IF NOT EXISTS tblexeatrequest (
    exeatid VARCHAR(40) NOT NULL PRIMARY KEY,
    userid VARCHAR(30) NOT NULL,
    houseid VARCHAR(40) NOT NULL,
    exeattype VARCHAR(20) NOT NULL DEFAULT 'external',
    reason VARCHAR(255) NOT NULL,
    dateout DATE NOT NULL,
    timeout TIME NULL,
    datereturn DATE NULL,
    timereturn TIME NULL,
    requestedatetime DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    decisionnote VARCHAR(255) NULL,
    decisionby VARCHAR(30) NULL,
    decisiondatetime DATETIME NULL,
    recordedby VARCHAR(30) NOT NULL,
    INDEX idx_exeat_student (userid),
    INDEX idx_exeat_house (houseid),
    INDEX idx_exeat_type (exeattype),
    INDEX idx_exeat_status (status),
    INDEX idx_exeat_requested (requestedatetime)
);

<?php

namespace App\Support;

/**
 * Plain-language explanations for the compliance controls Wazuh attaches to a
 * rule. Only codes that actually appear in this deployment's ruleset are
 * listed; anything else falls back to a generic note so the drawer never shows
 * an empty panel.
 *
 * Wording is a paraphrase of the published control text, not a quotation.
 */
class Compliance
{
    /**
     * What each framework is, shown above its control list.
     */
    public const FRAMEWORKS = [
        'PCI DSS' => 'Payment Card Industry Data Security Standard: the controls a system must meet to store, process or transmit cardholder data.',
        'HIPAA' => 'Health Insurance Portability and Accountability Act: US rules for protecting electronic protected health information (ePHI). 164.312 covers technical safeguards.',
        'GDPR' => 'EU General Data Protection Regulation: obligations for processing personal data. Article 32 covers security of processing.',
        'NIST 800-53' => 'US federal control catalogue. Codes here are the control family and number, e.g. AU.6 is Audit and Accountability control 6.',
        'TSC' => 'AICPA Trust Services Criteria, the basis of a SOC 2 report. CC is the common criteria series.',
    ];

    /**
     * framework => code => [title, summary]
     */
    public const CATALOG = [
        'PCI DSS' => [
            '2.2' => [
                'title' => 'Configuration standards for all system components',
                'summary' => 'Every system must be built from a documented hardening baseline that closes known vulnerabilities, and that baseline must be kept current. A rule in this group usually fires because a component drifted from its expected build.',
            ],
            '5.1' => [
                'title' => 'Deploy anti-malware on systems exposed to malicious software',
                'summary' => 'Endpoints that commonly attract malware must run anti-malware that is kept up to date and actively scanning. Alerts here suggest a detection, a stopped scan, or protection that is no longer running.',
            ],
            '8.1.2' => [
                'title' => 'Assign a unique ID to every user',
                'summary' => 'Nobody may share an account before touching system components or cardholder data. A finding here means an account could not be tied to one person.',
            ],
            '10.2.4' => [
                'title' => 'Log invalid logical access attempts',
                'summary' => 'Failed logins and rejected access must be recorded. These entries are the raw material for spotting password guessing and credential stuffing.',
            ],
            '10.2.5' => [
                'title' => 'Log use of and changes to identification and authentication credentials',
                'summary' => 'Creating, changing, disabling or elevating an account must leave a trail. This is how you reconstruct who granted themselves access after an incident.',
            ],
            '10.2.6' => [
                'title' => 'Log initialisation, stopping or pausing of audit logs',
                'summary' => 'Anyone who can silence logging can hide an intrusion, so starting, stopping or pausing the audit trail is itself an auditable event.',
            ],
            '10.2.7' => [
                'title' => 'Log creation and deletion of system-level objects',
                'summary' => 'New services, scheduled tasks, drivers or other system objects must be recorded, because persistence mechanisms are usually installed this way.',
            ],
            '10.6' => [
                'title' => 'Review logs and security events',
                'summary' => 'Collecting logs is not enough: they must be reviewed for anomalies and suspicious activity. Alerts mapped here are the ones a reviewer is expected to act on.',
            ],
            '10.6.1' => [
                'title' => 'Review security events and logs at least daily',
                'summary' => 'A named process must look at all security events and critical system logs every day. This is the control that turns alert volume into an operational duty, not a dashboard.',
            ],
            '11.2.1' => [
                'title' => 'Run quarterly internal vulnerability scans',
                'summary' => 'Internal scans must run at least every three months and findings must be remediated. A finding tied to this control is scanner output that has not been closed.',
            ],
            '11.2.3' => [
                'title' => 'Rescan after significant changes',
                'summary' => 'Any significant change to the environment invalidates the previous scan, so internal and external scans must be repeated.',
            ],
            '11.5' => [
                'title' => 'Detect and respond to changes on critical systems',
                'summary' => 'File integrity monitoring must alert on unauthorised modification, addition or deletion of critical files. Most alerts here come from the FIM module, which is why the rule group is usually syscheck.',
            ],
        ],

        'HIPAA' => [
            '164.310(d)(1)' => [
                'title' => 'Device and media controls: disposal',
                'summary' => 'Hardware and media holding ePHI must be disposed of so the data cannot be recovered. Related alerts cover removable media and device handling.',
            ],
            '164.312(a)(2)(iv)' => [
                'title' => 'Encryption and decryption (addressable)',
                'summary' => 'ePHI should be encrypted where reasonable. An alert here often means an unencrypted store or a failed decryption was observed.',
            ],
            '164.312.a.2.I' => [
                'title' => 'Unique user identification',
                'summary' => 'Each user needs a distinct identifier so activity can be attributed. Shared or missing identities break every downstream audit control.',
            ],
            '164.312.a.2.II' => [
                'title' => 'Emergency access procedure',
                'summary' => 'There must be a defined way to reach ePHI during an emergency. Use of break-glass or privileged accounts is what these alerts surface.',
            ],
            '164.312.b' => [
                'title' => 'Audit controls',
                'summary' => 'Record and examine activity in systems that hold ePHI. This is the broad logging control, so a large share of Windows and Linux audit events map here.',
            ],
            '164.312.c.1' => [
                'title' => 'Integrity: protect ePHI from improper alteration or destruction',
                'summary' => 'Changes to ePHI must be detectable. File integrity monitoring is the usual mechanism, and alerts here mean something changed.',
            ],
            '164.312.c.2' => [
                'title' => 'Mechanism to authenticate ePHI',
                'summary' => 'beyond detecting change, the system must be able to confirm the data is authentic and has not been substituted.',
            ],
            '164.312(e)(1)' => [
                'title' => 'Transmission security',
                'summary' => 'Guard ePHI while it moves across a network, so interception or tampering is prevented.',
            ],
            '164.312(e)(2)(i)' => [
                'title' => 'Integrity controls for transmission',
                'summary' => 'Confirm that ePHI was not altered in transit, typically through checksums or authenticated channels.',
            ],
            '164.312(e)(2)(ii)' => [
                'title' => 'Encryption for transmission',
                'summary' => 'Encrypt ePHI whenever it leaves a trusted boundary. Alerts here point at cleartext protocols or failed handshakes.',
            ],
        ],

        'GDPR' => [
            'II_5.1.f' => [
                'title' => 'Article 5(1)(f): integrity and confidentiality',
                'summary' => 'Personal data must be processed so it is protected against unauthorised or unlawful processing, and against accidental loss, destruction or damage.',
            ],
            'IV_32.2' => [
                'title' => 'Article 32(2): risk-based security measures',
                'summary' => 'When choosing the level of security, account must be taken of the risks of accidental or unlawful destruction, loss, alteration, or unauthorised disclosure of and access to personal data.',
            ],
            'IV_35.7.d' => [
                'title' => 'Article 35(7)(d): safeguards in a data protection impact assessment',
                'summary' => 'A DPIA must describe the safeguards, security measures and mechanisms that protect personal data. Alerts here are evidence that those measures are running.',
            ],
        ],

        'NIST 800-53' => [
            'AC.2' => [
                'title' => 'AC-2 Account management',
                'summary' => 'Accounts must be created, enabled, modified, reviewed, disabled and removed through a controlled process. Alerts cover account lifecycle events.',
            ],
            'AC.7' => [
                'title' => 'AC-7 Unsuccessful logon attempts',
                'summary' => 'Limit consecutive failed logins and react to them. Repeated failures here are the signal for guessing, spraying or a misconfigured service account.',
            ],
            'AC.9' => [
                'title' => 'AC-9 Previous logon notification',
                'summary' => 'Tell a user about their last login, so an unexpected one is noticed quickly. Related alerts cover interactive and remote logons.',
            ],
            'AU.5' => [
                'title' => 'AU-5 Response to audit processing failures',
                'summary' => 'If audit logging fails or fills up, that must be handled and reported, not silently ignored. An alert here means the audit pipeline itself is in trouble.',
            ],
            'AU.6' => [
                'title' => 'AU-6 Audit review, analysis and reporting',
                'summary' => 'Audit records must be reviewed and analysed, and findings reported. This is the control behind the expectation that someone reads these alerts.',
            ],
            'AU.14' => [
                'title' => 'AU-14 Session audit',
                'summary' => 'Record enough of a session to reconstruct what happened, including who did it and what they touched.',
            ],
            'CM.1' => [
                'title' => 'CM-1 Configuration management policy and procedures',
                'summary' => 'A documented configuration management process must exist and be followed. Alerts here mean a change happened outside that process.',
            ],
            'IA.4' => [
                'title' => 'IA-4 Identifier management',
                'summary' => 'Identifiers must be managed so they map to one person or device over its lifetime, and are not reused.',
            ],
            'SC.8' => [
                'title' => 'SC-8 Transmission confidentiality and integrity',
                'summary' => 'Protect information in transit against disclosure and alteration, usually with encryption and integrity checks.',
            ],
            'SI.3' => [
                'title' => 'SI-3 Malicious code protection',
                'summary' => 'Detect and stop malicious code at entry points and on endpoints. Alerts here come from anti-malware detections or protection that stopped running.',
            ],
            'SI.4' => [
                'title' => 'SI-4 Information system monitoring',
                'summary' => 'Monitor the system for attacks, intrusions and indicators of potential incidents, and tune what you monitor over time.',
            ],
            'SI.7' => [
                'title' => 'SI-7 Software, firmware and information integrity',
                'summary' => 'Detect unauthorised changes to software, firmware and information. In practice this is file integrity monitoring and binary or registry checks.',
            ],
        ],

        'TSC' => [
            'A1.2' => [
                'title' => 'A1.2 Availability: environmental and recovery protections',
                'summary' => 'The entity maintains the protections, backup processes and recovery infrastructure needed to meet availability commitments.',
            ],
            'CC6.1' => [
                'title' => 'CC6.1 Logical access security',
                'summary' => 'Access to protected assets is restricted through logical security software, infrastructure and architecture, and is protected from security events.',
            ],
            'CC6.3' => [
                'title' => 'CC6.3 Role-based access authorisation',
                'summary' => 'Access is granted, modified and removed according to roles and responsibilities, and re-evaluated when the design changes.',
            ],
            'CC6.8' => [
                'title' => 'CC6.8 Prevent and detect malicious software',
                'summary' => 'Controls must prevent, or detect and act on, the introduction of unauthorised or malicious software.',
            ],
            'CC7.1' => [
                'title' => 'CC7.1 Detect configuration changes and new vulnerabilities',
                'summary' => 'Detection and monitoring procedures must spot configuration changes that introduce vulnerabilities, and exposure to newly discovered ones.',
            ],
            'CC7.2' => [
                'title' => 'CC7.2 Monitor for anomalies and security events',
                'summary' => 'System components are monitored for anomalies indicative of malicious acts, disasters or errors, and anomalies are analysed to decide whether they are security events. This is the most common mapping for Windows security-channel alerts.',
            ],
            'CC7.3' => [
                'title' => 'CC7.3 Evaluate and respond to security events',
                'summary' => 'Security events are evaluated to decide whether they caused a failure to meet objectives, and if so, action is taken. This is the control that expects a triage decision and a recorded outcome.',
            ],
            'CC8.1' => [
                'title' => 'CC8.1 Change management',
                'summary' => 'Changes to infrastructure, data, software and procedures are authorised, designed, tested, approved, documented and implemented.',
            ],
            'PI1.4' => [
                'title' => 'PI1.4 Processing integrity: complete and accurate output',
                'summary' => 'Output is made available completely, accurately and on time to authorised users, as specified.',
            ],
            'PI1.5' => [
                'title' => 'PI1.5 Processing integrity: complete and accurate storage',
                'summary' => 'Inputs, in-process items and outputs are stored completely, accurately and on time, so records can be relied on later.',
            ],
        ],
    ];

    /**
     * Look up one control. Falls back to a generic shape so the drawer always
     * has something to show, and reports `found = false` when the code is not
     * in the catalogue.
     *
     * @return array{framework: string, code: string, title: string, summary: string, found: bool}
     */
    public static function describe(string $framework, string $code): array
    {
        $code = trim($code);
        $entry = self::CATALOG[$framework][$code] ?? null;

        if ($entry !== null) {
            return [
                'framework' => $framework,
                'code' => $code,
                'title' => $entry['title'],
                'summary' => $entry['summary'],
                'found' => true,
            ];
        }

        return [
            'framework' => $framework,
            'code' => $code,
            'title' => $code,
            'summary' => 'No description is bundled for this control yet. It is a requirement of '
                . $framework . ' that the rule which triggered this alert is meant to help satisfy.',
            'found' => false,
        ];
    }

    /**
     * Everything the drawer needs, keyed by framework then code.
     */
    public static function catalogue(): array
    {
        return [
            'frameworks' => self::FRAMEWORKS,
            'controls' => self::CATALOG,
        ];
    }
}

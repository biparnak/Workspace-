<?php
require_once __DIR__ . '/_header.php';

$serverType    = get_setting('dns_server_type', 'none');
$serverInstalled = get_setting('dns_server_installed', '0');
$serverIP      = get_server_ip();
$serverDomain  = get_setting('dns_server_domain', '');
$freednsUser   = get_setting('freedns_username', '');
$freednsApiKey = get_setting('freedns_api_key', '');

$logs = [];
$logFile = __DIR__ . '/../dns_install.log';
if (file_exists($logFile)) {
    $logs = array_slice(array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)), 0, 50);
}

$regCount = (int)db()->query('SELECT COUNT(*) FROM registrations WHERE active = 1')->fetchColumn();
$records = [];
if ($regCount > 0) {
    $records = db()->query(
        'SELECT r.subdomain, d.name AS domain, dr.type, dr.value, dr.ttl
         FROM registrations r
         JOIN domains d ON d.id = r.domain_id
         JOIN dns_records dr ON dr.registration_id = r.id
         WHERE r.active = 1
         ORDER BY d.name, r.subdomain'
    )->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'install_bind9') {
        $domain = strtolower(trim($_POST['server_domain'] ?? ''));
        $ip = trim($_POST['server_ip'] ?? get_server_ip());

        if (empty($domain)) {
            flash('admin', 'Server domain is required.', 'error');
        } else {
            set_setting('dns_server_ip', $ip);
            set_setting('dns_server_domain', $domain);
            set_setting('dns_server_type', 'bind9');

            $installLog = [];
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Starting Bind9 installation...';

            $commands = [
                'apt-get update -qq',
                'apt-get install -y bind9 bind9utils bind9-doc',
                'systemctl enable named',
            ];

            $allOk = true;
            foreach ($commands as $cmd) {
                exec($cmd . ' 2>&1', $output, $returnCode);
                $installLog[] = '[' . date('Y-m-d H:i:s') . '] Running: ' . $cmd . ' (exit: ' . $returnCode . ')';
                if ($returnCode !== 0 && $cmd !== 'apt-get update -qq') {
                    $allOk = false;
                    $installLog[] = '[' . date('Y-m-d H:i:s') . '] WARNING: Command returned non-zero exit code.';
                }
                $output = [];
            }

            $bindDir = '/etc/bind';
            if (is_dir($bindDir) || @mkdir($bindDir, 0755, true)) {
                $namedConf = "options {\n";
                $namedConf .= "    directory \"/var/cache/bind\";\n";
                $namedConf .= "    dnssec-validation auto;\n";
                $namedConf .= "    allow-query { any; };\n";
                $namedConf .= "    recursion yes;\n";
                $namedConf .= "    allow-recursion { any; };\n";
                $namedConf .= "    listen-on { $ip; };\n";
                $namedConf .= "    listen-on-v6 { any; };\n";
                $namedConf .= "    forwarders {\n";
                $namedConf .= "        8.8.8.8;\n";
                $namedConf .= "        8.8.4.4;\n";
                $namedConf .= "        1.1.1.1;\n";
                $namedConf .= "    };\n";
                $namedConf .= "};\n\n";
                $namedConf .= 'zone "." { type hints; file "/var/cache/bind/db.root"; };' . "\n";
                @file_put_contents($bindDir . '/named.conf.options', $namedConf);
                $installLog[] = '[' . date('Y-m-d H:i:s') . '] Written named.conf.options';
            }

            $zoneFile = "; FreeDNS Zone for $domain\n";
            $zoneFile .= "\$TTL 3600\n";
            $zoneFile .= "@       IN      SOA     ns1.$domain. admin.$domain. (\n";
            $zoneFile .= "                        " . date('Ymd') . "01 ; Serial\n";
            $zoneFile .= "                        3600       ; Refresh\n";
            $zoneFile .= "                        900        ; Retry\n";
            $zoneFile .= "                        604800     ; Expire\n";
            $zoneFile .= "                        3600 )     ; Minimum TTL\n\n";
            $zoneFile .= "@       IN      NS      ns1.$domain.\n";
            $zoneFile .= "@       IN      NS      ns2.$domain.\n";
            $zoneFile .= "@       IN      A       $ip\n";
            $zoneFile .= "ns1     IN      A       $ip\n";
            $zoneFile .= "ns2     IN      A       $ip\n";
            $zoneFile .= "www     IN      A       $ip\n";

            foreach ($records as $rec) {
                $sub = ($rec['subdomain'] === '@') ? '' : $rec['subdomain'] . '.';
                $zoneFile .= "$sub  IN  {$rec['type']}  ";
                if ($rec['type'] === 'MX') {
                    $zoneFile .= "{$rec['value']}.";
                } else {
                    $zoneFile .= $rec['value'];
                }
                $zoneFile .= "\n";
            }

            if (!is_dir($bindDir . '/zones')) @mkdir($bindDir . '/zones', 0755, true);
            @file_put_contents($bindDir . '/zones/db.' . $domain, $zoneFile);
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Written zone file for ' . $domain;
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Bind9 zone contains ' . count($records) . ' DNS records from ' . $regCount . ' registrations.';

            exec('systemctl restart named 2>&1', $restartOut, $restartCode);
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Restart named (exit: ' . $restartCode . ')';
            if ($restartCode !== 0) {
                $installLog[] = '[' . date('Y-m-d H:i:s') . '] Restart output: ' . implode('; ', $restartOut);
            }

            file_put_contents($logFile, implode("\n", $installLog) . "\n", FILE_APPEND);

            set_setting('dns_server_installed', $allOk ? '1' : '0');
            flash('admin', 'Bind9 installation ' . ($allOk ? 'completed' : 'completed with warnings') . '. Check server logs for details.', $allOk ? 'success' : 'info');
        }
        redirect('admin/dns-server.php');
    }

    if ($action === 'install_dnsmasq') {
        $domain = strtolower(trim($_POST['server_domain'] ?? ''));
        $ip = trim($_POST['server_ip'] ?? get_server_ip());

        if (empty($domain)) {
            flash('admin', 'Server domain is required.', 'error');
        } else {
            set_setting('dns_server_ip', $ip);
            set_setting('dns_server_domain', $domain);
            set_setting('dns_server_type', 'dnsmasq');

            $installLog = [];
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Starting DNSMasq installation...';

            $commands = [
                'apt-get update -qq',
                'apt-get install -y dnsmasq',
            ];

            $allOk = true;
            foreach ($commands as $cmd) {
                exec($cmd . ' 2>&1', $output, $returnCode);
                $installLog[] = '[' . date('Y-m-d H:i:s') . '] Running: ' . $cmd . ' (exit: ' . $returnCode . ')';
                if ($returnCode !== 0) {
                    $allOk = false;
                }
                $output = [];
            }

            $dnsmasqConf = "# FreeDNS DNSMasq Configuration\n";
            $dnsmasqConf .= "domain=$domain\n";
            $dnsmasqConf .= "local=/$domain/\n";
            $dnsmasqConf .= "expand-hosts\n";
            $dnsmasqConf .= "listen-address=$ip\n";
            $dnsmasqConf .= "bind-interfaces\n";
            $dnsmasqConf .= "server=8.8.8.8\n";
            $dnsmasqConf .= "server=8.8.4.4\n";
            $dnsmasqConf .= "server=1.1.1.1\n";
            $dnsmasqConf .= "cache-size=10000\n";
            $dnsmasqConf .= "log-queries\n";
            $dnsmasqConf .= "log-facility=/var/log/dnsmasq.log\n\n";

            foreach ($records as $rec) {
                $sub = ($rec['subdomain'] === '@') ? '' : $rec['subdomain'] . '.';
                if ($rec['type'] === 'A') {
                    $dnsmasqConf .= "address=/${sub}$domain/${rec['value']}\n";
                } elseif ($rec['type'] === 'CNAME') {
                    $dnsmasqConf .= "cname=${sub}$domain,${rec['value']}\n";
                }
            }

            @file_put_contents('/etc/dnsmasq.d/freedns.conf', $dnsmasqConf);
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Written /etc/dnsmasq.d/freedns.conf';
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] DNSMasq configured with ' . count($records) . ' DNS records.';

            exec('systemctl restart dnsmasq 2>&1', $restartOut, $restartCode);
            $installLog[] = '[' . date('Y-m-d H:i:s') . '] Restart dnsmasq (exit: ' . $restartCode . ')';

            file_put_contents($logFile, implode("\n", $installLog) . "\n", FILE_APPEND);
            set_setting('dns_server_installed', $allOk ? '1' : '0');
            flash('admin', 'DNSMasq installation ' . ($allOk ? 'completed' : 'completed with warnings') . '.', $allOk ? 'success' : 'info');
        }
        redirect('admin/dns-server.php');
    }

    if ($action === 'update_server_ip') {
        $ip = trim($_POST['server_ip'] ?? '');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            set_setting('dns_server_ip', $ip);
            flash('admin', 'Server IP updated to ' . $ip . '.', 'success');
        } else {
            flash('admin', 'Invalid IP address.', 'error');
        }
        redirect('admin/dns-server.php');
    }

    if ($action === 'update_freedns') {
        $fu = trim($_POST['freedns_username'] ?? '');
        $fk = trim($_POST['freedns_api_key'] ?? '');
        set_setting('freedns_username', $fu);
        set_setting('freedns_api_key', $fk);
        flash('admin', 'FreeDNS credentials saved.', 'success');
        redirect('admin/dns-server.php');
    }

    if ($action === 'sync_records') {
        $domain = get_setting('dns_server_domain', '');
        $type = get_setting('dns_server_type', 'none');
        if ($type === 'none' || empty($domain)) {
            flash('admin', 'No DNS server configured yet.', 'error');
        } else {
            $syncLog = '[' . date('Y-m-d H:i:s') . '] Syncing ' . count($records) . ' records to ' . $type . '...';
            file_put_contents($logFile, $syncLog . "\n", FILE_APPEND);

            if ($type === 'bind9') {
                $ip = get_setting('dns_server_ip', '127.0.0.1');
                $zoneFile = "; FreeDNS Auto-Sync Zone for $domain\n";
                $zoneFile .= "; Last synced: " . date('Y-m-d H:i:s') . "\n";
                $zoneFile .= "\$TTL 3600\n";
                $zoneFile .= "@       IN      SOA     ns1.$domain. admin.$domain. (\n";
                $zoneFile .= "                        " . date('Ymd') . "01 ; Serial\n";
                $zoneFile .= "                        3600       ; Refresh\n";
                $zoneFile .= "                        900        ; Retry\n";
                $zoneFile .= "                        604800     ; Expire\n";
                $zoneFile .= "                        3600 )     ; Minimum TTL\n\n";
                $zoneFile .= "@       IN      NS      ns1.$domain.\n";
                $zoneFile .= "@       IN      NS      ns2.$domain.\n";
                $zoneFile .= "@       IN      A       $ip\n";
                $zoneFile .= "ns1     IN      A       $ip\n";
                $zoneFile .= "ns2     IN      A       $ip\n";
                $zoneFile .= "www     IN      A       $ip\n";
                foreach ($records as $rec) {
                    $sub = ($rec['subdomain'] === '@') ? '' : $rec['subdomain'] . '.';
                    $zoneFile .= "$sub  IN  {$rec['type']}  " . $rec['value'] . "\n";
                }
                @file_put_contents("/etc/bind/zones/db.$domain", $zoneFile);
                exec("systemctl restart named 2>&1", $out, $rc);
                $syncLog = '[' . date('Y-m-d H:i:s') . '] Bind9 zone synced and service restarted (exit: ' . $rc . ')';
            } elseif ($type === 'dnsmasq') {
                $dnsmasqConf = "# FreeDNS Auto-Sync\n";
                $dnsmasqConf .= "domain=$domain\n";
                $dnsmasqConf .= "local=/$domain/\n";
                $dnsmasqConf .= "expand-hosts\n";
                $dnsmasqConf .= "listen-address=" . get_setting('dns_server_ip', '127.0.0.1') . "\n";
                $dnsmasqConf .= "server=8.8.8.8\nserver=8.8.4.4\nserver=1.1.1.1\n\n";
                foreach ($records as $rec) {
                    $sub = ($rec['subdomain'] === '@') ? '' : $rec['subdomain'] . '.';
                    if ($rec['type'] === 'A') {
                        $dnsmasqConf .= "address=/${sub}$domain/${rec['value']}\n";
                    } elseif ($rec['type'] === 'CNAME') {
                        $dnsmasqConf .= "cname=${sub}$domain,${rec['value']}\n";
                    }
                }
                @file_put_contents('/etc/dnsmasq.d/freedns.conf', $dnsmasqConf);
                exec("systemctl restart dnsmasq 2>&1", $out, $rc);
                $syncLog = '[' . date('Y-m-d H:i:s') . '] DNSMasq synced and restarted (exit: ' . $rc . ')';
            }
            file_put_contents($logFile, $syncLog . "\n", FILE_APPEND);
            flash('admin', 'DNS records synced successfully (' . count($records) . ' records).', 'success');
        }
        redirect('admin/dns-server.php');
    }

    if ($action === 'reinstall') {
        set_setting('dns_server_type', 'none');
        set_setting('dns_server_installed', '0');
        flash('admin', 'DNS server reset. You can reconfigure now.', 'info');
        redirect('admin/dns-server.php');
    }
}
?>

        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h2>DNS Server Installation</h2>
            <p class="sub" style="margin-bottom:0;">Install and configure a real DNS server (Bind9 or DNSMasq) that automatically resolves all registered subdomains.</p>
        </div>

        <div class="dash-stats" style="margin-bottom:24px;">
            <div class="stat-card" style="border-left:4px solid <?php echo $serverInstalled === '1' ? 'var(--nc-green)' : 'var(--nc-muted)'; ?>;">
                <div class="stat-value" style="font-size:1.3rem;"><?php echo $serverType === 'none' ? 'Not installed' : strtoupper($serverType); ?></div>
                <div class="stat-label">DNS Server</div>
            </div>
            <div class="stat-card" style="border-left:4px solid var(--nc-blue);">
                <div class="stat-value" style="font-size:1.3rem;"><?php echo $serverIP ?: 'Auto-detect'; ?></div>
                <div class="stat-label">Server IP</div>
            </div>
            <div class="stat-card" style="border-left:4px solid var(--nc-orange);">
                <div class="stat-value" style="font-size:1.3rem;"><?php echo $serverDomain ?: 'Not set'; ?></div>
                <div class="stat-label">Server Domain</div>
            </div>
            <div class="stat-card" style="border-left:4px solid var(--nc-teal);">
                <div class="stat-value" style="font-size:1.3rem;"><?php echo $regCount; ?></div>
                <div class="stat-label">Active Subdomains</div>
            </div>
        </div>

        <?php if ($serverInstalled === '1'): ?>
            <div class="card wide" style="max-width:none;margin-bottom:24px;border-left:4px solid var(--nc-green);">
                <h3 style="color:var(--nc-green);">&#10003; <?php echo strtoupper($serverType); ?> is Installed & Running</h3>
                <p class="sub">Your DNS server is configured and serving records for <strong><?php echo e($serverDomain); ?></strong>.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
                    <form method="post" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="sync_records">
                        <button class="btn btn-primary" type="submit">&#8635; Sync Records Now</button>
                    </form>
                    <form method="post" style="display:inline;" data-confirm="This will reset DNS server config. Continue?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reinstall">
                        <button class="btn btn-danger" type="submit">Reset / Reinstall</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($serverInstalled === '0' || $serverType === 'none'): ?>
            <div class="card wide" style="max-width:none;margin-bottom:24px;">
                <h3>Choose Your DNS Server</h3>
                <p class="sub">Select a DNS server to install. Both options will automatically configure zones for all registered subdomains.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
                    <div style="border:2px solid var(--nc-border);border-radius:var(--radius);padding:24px;text-align:center;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--nc-blue)'" onmouseout="this.style.borderColor='var(--nc-border)'">
                        <div style="font-size:2.5rem;margin-bottom:10px;">&#128225;</div>
                        <h3>Bind9</h3>
                        <p class="sub" style="font-size:.85rem;">Industry-standard DNS server. Full zone file support, BIND configuration, ideal for production.</p>
                        <ul style="text-align:left;font-size:.85rem;color:var(--nc-text);margin:12px 0;list-style:disc;padding-left:20px;">
                            <li>Full DNS zone management</li>
                            <li>SOA, NS, A, AAAA, CNAME, MX, TXT records</li>
                            <li>Automatic zone file generation</li>
                            <li>Production-grade DNS server</li>
                        </ul>
                        <form method="post" style="margin-top:16px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="install_bind9">
                            <input type="hidden" name="server_domain" id="bind_domain">
                            <input type="hidden" name="server_ip" id="bind_ip">
                            <button type="submit" class="btn btn-primary btn-block" onclick="document.getElementById('bind_domain').value=document.getElementById('serverDomain').value;document.getElementById('bind_ip').value=document.getElementById('serverIp').value;">Install Bind9 &rarr;</button>
                        </form>
                    </div>
                    <div style="border:2px solid var(--nc-border);border-radius:var(--radius);padding:24px;text-align:center;transition:border-color .2s;" onmouseover="this.style.borderColor='var(--nc-orange)'" onmouseout="this.style.borderColor='var(--nc-border)'">
                        <div style="font-size:2.5rem;margin-bottom:10px;">&#9889;</div>
                        <h3>DNSMasq</h3>
                        <p class="sub" style="font-size:.85rem;">Lightweight DNS & DHCP. Simple, fast, great for smaller setups and home networks.</p>
                        <ul style="text-align:left;font-size:.85rem;color:var(--nc-text);margin:12px 0;list-style:disc;padding-left:20px;">
                            <li>Lightweight and fast</li>
                            <li>Simple A/CNAME record support</li>
                            <li>Caching DNS resolver built-in</li>
                            <li>Easy to maintain</li>
                        </ul>
                        <form method="post" style="margin-top:16px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="install_dnsmasq">
                            <input type="hidden" name="server_domain" id="masq_domain">
                            <input type="hidden" name="server_ip" id="masq_ip">
                            <button type="submit" class="btn btn-orange btn-block" onclick="document.getElementById('masq_domain').value=document.getElementById('serverDomain').value;document.getElementById('masq_ip').value=document.getElementById('serverIp').value;">Install DNSMasq &rarr;</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>Server Configuration</h3>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_server_ip">
                <div class="form-row" style="grid-template-columns:2fr 1fr 1fr;align-items:end;">
                    <div class="form-group">
                        <label>Server Domain (for DNS zone)</label>
                        <input type="text" id="serverDomain" value="<?php echo e($serverDomain); ?>" placeholder="dns.yourdomain.com" required>
                        <div class="hint">e.g. ns1.yourdomain.com - This is the nameserver domain.</div>
                    </div>
                    <div class="form-group">
                        <label>Server IP Address</label>
                        <input type="text" id="serverIp" name="server_ip" value="<?php echo e($serverIP); ?>" placeholder="Auto-detect">
                        <div class="hint">IP of this server (auto-detected if empty).</div>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-block" type="submit">Save IP</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>FreeDNS API Integration</h3>
            <p class="sub">Connect to freedns.afraid.org to pull public domains or sync records.</p>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_freedns">
                <div class="form-row" style="grid-template-columns:1fr 2fr 1fr;align-items:end;">
                    <div class="form-group">
                        <label>FreeDNS Username</label>
                        <input type="text" name="freedns_username" value="<?php echo e($freednsUser); ?>" placeholder="your_username">
                    </div>
                    <div class="form-group">
                        <label>FreeDNS API Key</label>
                        <input type="text" name="freedns_api_key" value="<?php echo e($freednsApiKey); ?>" placeholder="Your API key from freedns.afraid.org">
                        <div class="hint">Get your API key from freedns.afraid.org &rarr; API &rarr; XML &amp; JSON API.</div>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-block" type="submit">Save Credentials</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($serverInstalled === '1' && !empty($records)): ?>
        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>DNS Records to Sync (<?php echo count($records); ?> records)</h3>
            <div class="table-wrap" style="box-shadow:none;max-height:400px;overflow-y:auto;">
                <table class="table">
                    <thead><tr><th>Subdomain</th><th>Type</th><th>Value</th><th>TTL</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td class="mono"><?php echo e(($rec['subdomain'] === '@' ? '' : $rec['subdomain'] . '.') . $rec['domain']); ?></td>
                            <td><span class="pill pill-on"><?php echo e($rec['type']); ?></span></td>
                            <td class="mono" style="font-size:.85rem;"><?php echo e($rec['value']); ?></td>
                            <td><?php echo (int)$rec['ttl']; ?>s</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($logs)): ?>
        <div class="card wide" style="max-width:none;margin-bottom:24px;">
            <h3>Installation Logs</h3>
            <div style="background:#1a1a2e;border-radius:var(--radius);padding:16px;max-height:300px;overflow-y:auto;">
                <?php foreach ($logs as $log): ?>
                    <div style="color:#a0aec0;font-family:'Consolas','Courier New',monospace;font-size:.82rem;line-height:1.7;white-space:pre-wrap;"><?php echo htmlspecialchars($log); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card wide" style="max-width:none;">
            <h3>Setup Instructions</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px;">
                <div style="padding:16px;background:var(--nc-bg);border-radius:var(--radius);">
                    <h4 style="margin-bottom:8px;">Prerequisites</h4>
                    <ul style="font-size:.88rem;color:var(--nc-text);list-style:decimal;padding-left:20px;list-style-position:inside;">
                        <li>Linux VPS with root/sudo access</li>
                        <li>Domain name pointed to server IP</li>
                        <li>Ports 53 (UDP/TCP) open in firewall</li>
                    </ul>
                </div>
                <div style="padding:16px;background:var(--nc-bg);border-radius:var(--radius);">
                    <h4 style="margin-bottom:8px;">After Installation</h4>
                    <ul style="font-size:.88rem;color:var(--nc-text);list-style:decimal;padding-left:20px;list-style-position:inside;">
                        <li>Register nameservers at your domain registrar</li>
                        <li>Sync DNS records from admin panel</li>
                        <li>Test with: <code>dig @<?php echo e($serverIP ?: 'YOUR_IP'); ?> test.<?php echo e($serverDomain); ?></code></li>
                    </ul>
                </div>
            </div>
        </div>

<?php require_once __DIR__ . '/_footer.php';

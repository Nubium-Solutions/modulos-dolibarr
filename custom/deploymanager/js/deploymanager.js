(function() {
    'use strict';

    var DOL_URL_ROOT = (typeof window.DOL_URL_ROOT !== 'undefined') ? window.DOL_URL_ROOT : '';
    if (!DOL_URL_ROOT) {
        var s = document.querySelector('script[src*="deploymanager"]');
        if (s) DOL_URL_ROOT = s.src.replace(/\/custom\/deploymanager\/.*$/, '');
    }

    document.addEventListener('DOMContentLoaded', function() {
        initTestSSH();
        initScanButtons();
        initDeployWizard();
        initAutodiscover();
    });

    function initTestSSH() {
        document.querySelectorAll('.dm-test-ssh').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                var result = document.querySelector('.dm-test-result[data-id="' + id + '"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
                result.textContent = '';
                result.className = 'dm-test-result';

                fetch(DOL_URL_ROOT + '/custom/deploymanager/ajax/test_connection.php?id=' + id)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        result.textContent = data.message || (data.ok ? 'OK' : 'Error');
                        result.classList.add(data.ok ? 'ok' : 'fail');
                        btn.innerHTML = '<i class="fa fa-plug"></i> Test';
                        btn.disabled = false;
                    })
                    .catch(function() {
                        result.textContent = 'Error de red';
                        result.classList.add('fail');
                        btn.innerHTML = '<i class="fa fa-plug"></i> Test';
                        btn.disabled = false;
                    });
            });
        });
    }

    function initScanButtons() {
        document.querySelectorAll('.dm-scan-one').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                fetch(DOL_URL_ROOT + '/custom/deploymanager/ajax/scan_instance.php?id=' + id)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.ok) {
                            btn.innerHTML = '<i class="fa fa-check"></i> ' + data.modules.length + ' módulos';
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            btn.innerHTML = '<i class="fa fa-times"></i> Error';
                            alert(data.error || 'Error escaneando');
                            btn.disabled = false;
                        }
                    })
                    .catch(function() {
                        btn.innerHTML = '<i class="fa fa-times"></i> Error';
                        btn.disabled = false;
                    });
            });
        });

        var scanAll = document.getElementById('dm-scan-all');
        if (scanAll) {
            scanAll.addEventListener('click', function() {
                var buttons = Array.from(document.querySelectorAll('.dm-scan-one'));
                var idx = 0;
                scanAll.disabled = true;
                scanAll.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 0/' + buttons.length;

                function scanNext() {
                    if (idx >= buttons.length) {
                        scanAll.innerHTML = '<i class="fa fa-check"></i> ' + buttons.length + '/' + buttons.length;
                        setTimeout(function() { location.reload(); }, 1000);
                        return;
                    }
                    var btn = buttons[idx];
                    var id = btn.getAttribute('data-id');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                    fetch(DOL_URL_ROOT + '/custom/deploymanager/ajax/scan_instance.php?id=' + id)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.ok) {
                                btn.innerHTML = '<i class="fa fa-check"></i> ' + data.modules.length;
                            } else {
                                btn.innerHTML = '<i class="fa fa-times"></i>';
                            }
                        })
                        .catch(function() {
                            btn.innerHTML = '<i class="fa fa-times"></i>';
                        })
                        .finally(function() {
                            idx++;
                            scanAll.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + idx + '/' + buttons.length;
                            scanNext();
                        });
                }

                scanNext();
            });
        }
    }

    function initDeployWizard() {
        if (typeof DM_WIZARD_DATA === 'undefined') return;

        var data = DM_WIZARD_DATA;
        var selectedModules = [];
        var selectedInstances = [];

        var moduleSelect = document.getElementById('dm-module-select');
        var sourceInfoDiv = document.getElementById('dm-source-info');
        var step1Next = document.getElementById('dm-step1-next');
        var step2Next = document.getElementById('dm-step2-next');
        var step2Back = document.getElementById('dm-step2-back');
        var step3Back = document.getElementById('dm-step3-back');
        var deployBtn = document.getElementById('dm-deploy-btn');

        if (!moduleSelect) return;

        var selectedContainer = document.getElementById('dm-selected-modules');

        function updateSelectedUI() {
            var html = '';
            selectedModules.forEach(function(m, idx) {
                html += '<span style="display:inline-block;background:#e8f4fd;border:1px solid #b8daff;border-radius:4px;padding:4px 8px;margin:4px 4px 4px 0;font-size:13px;">';
                html += m.name;
                html += ' <small style="color:#666;">(' + m.source.domain + ' v' + m.source.version + ')</small>';
                html += ' <a href="#" data-idx="' + idx + '" style="color:#e74c3c;text-decoration:none;font-weight:bold;margin-left:4px;" class="dm-remove-mod">&times;</a>';
                html += '</span>';
            });
            selectedContainer.innerHTML = html;
            sourceInfoDiv.style.display = 'none';
            step1Next.disabled = (selectedModules.length === 0);
        }

        moduleSelect.addEventListener('change', function() {
            var modId = moduleSelect.value;
            if (!modId) return;

            for (var i = 0; i < selectedModules.length; i++) {
                if (selectedModules[i].id === modId) {
                    moduleSelect.value = '';
                    return;
                }
            }

            var src = data.sourceByModule[modId];
            if (src) {
                selectedModules.push({ id: modId, name: moduleSelect.options[moduleSelect.selectedIndex].textContent, source: src });
                updateSelectedUI();
            } else {
                sourceInfoDiv.innerHTML = '<span style="color:#e74c3c;">Sin instancia origen para este módulo</span>';
                sourceInfoDiv.style.display = '';
            }
            moduleSelect.value = '';
        });

        selectedContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('dm-remove-mod')) {
                e.preventDefault();
                var idx = parseInt(e.target.getAttribute('data-idx'), 10);
                selectedModules.splice(idx, 1);
                updateSelectedUI();
            }
        });

        if (data.preselectedModule) {
            var opts = moduleSelect.options;
            for (var i = 0; i < opts.length; i++) {
                if (opts[i].value && data.sourceByModule[opts[i].value]) {
                    var slug = opts[i].textContent.match(/\(([^)]+)\)/);
                    if (slug && slug[1] === data.preselectedModule) {
                        moduleSelect.value = opts[i].value;
                        moduleSelect.dispatchEvent(new Event('change'));
                        break;
                    }
                }
            }
        }

        step1Next.addEventListener('click', function() {
            document.getElementById('dm-step1').style.display = 'none';
            document.getElementById('dm-step2').style.display = '';
            renderInstances();
        });

        step2Back.addEventListener('click', function() {
            document.getElementById('dm-step2').style.display = 'none';
            document.getElementById('dm-step1').style.display = '';
        });

        function renderInstances() {
            var tbody = document.getElementById('dm-instances-body');
            tbody.innerHTML = '';

            var sourceIds = {};
            selectedModules.forEach(function(m) { sourceIds[m.source.instance_id] = true; });

            data.instances.forEach(function(inst) {
                if (sourceIds[inst.rowid]) return;

                var allSame = true;
                var hasAny = false;
                var versionInfo = [];

                selectedModules.forEach(function(m) {
                    var ver = (data.instModVersions[inst.rowid] && data.instModVersions[inst.rowid][m.id]) ? data.instModVersions[inst.rowid][m.id] : '';
                    if (ver) hasAny = true;
                    if (!ver || ver !== m.source.version) allSame = false;
                    versionInfo.push(ver);
                });

                var allUpToDate = hasAny && allSame;
                var noModules = !hasAny;
                var tr = document.createElement('tr');

                var checkboxCell = '';
                if (allUpToDate) {
                    tr.style.opacity = '0.4';
                    checkboxCell = '<td><i class="fa fa-check-circle" style="color:#22c55e;font-size:15px;margin-left:2px;" title="Ya actualizado"></i></td>';
                } else if (noModules) {
                    tr.style.opacity = '0.5';
                    checkboxCell = '<td><input type="checkbox" class="dm-inst-cb" data-id="' + inst.rowid + '"></td>';
                } else {
                    checkboxCell = '<td><input type="checkbox" class="dm-inst-cb" data-id="' + inst.rowid + '"></td>';
                }

                var verDisplay = versionInfo.length === 1
                    ? (versionInfo[0] || '<span style="color:#999;">—</span>')
                    : versionInfo.map(function(v) { return v || '—'; }).join(', ');

                tr.innerHTML =
                    checkboxCell +
                    '<td>' + inst.name + '</td>' +
                    '<td>' + inst.server_name + '</td>' +
                    '<td>' + inst.environment + '</td>' +
                    '<td>' + verDisplay + '</td>';

                tbody.appendChild(tr);
            });

            updateStep2Next();
        }

        document.getElementById('dm-instances-body').addEventListener('change', updateStep2Next);

        function updateStep2Next() {
            var checked = document.querySelectorAll('.dm-inst-cb:checked');
            step2Next.disabled = (checked.length === 0);
        }

        document.getElementById('dm-select-all').addEventListener('click', function() {
            document.querySelectorAll('.dm-inst-cb:not(:disabled)').forEach(function(cb) { cb.checked = true; });
            updateStep2Next();
        });

        document.getElementById('dm-deselect-all').addEventListener('click', function() {
            document.querySelectorAll('.dm-inst-cb').forEach(function(cb) { cb.checked = false; });
            updateStep2Next();
        });

        step2Next.addEventListener('click', function() {
            selectedInstances = [];
            document.querySelectorAll('.dm-inst-cb:checked').forEach(function(cb) {
                selectedInstances.push(parseInt(cb.getAttribute('data-id'), 10));
            });

            var summary = '<p><strong>Módulos:</strong></p><ul>';
            selectedModules.forEach(function(m) {
                summary += '<li>' + m.name + ' — desde ' + m.source.domain + ' (v' + m.source.version + ')</li>';
            });
            summary += '</ul>';
            summary += '<p><strong>Instancias destino:</strong> ' + selectedInstances.length + '</p>';
            summary += '<ul>';
            document.querySelectorAll('.dm-inst-cb:checked').forEach(function(cb) {
                var name = cb.closest('tr').cells[1].textContent;
                summary += '<li>' + name + '</li>';
            });
            summary += '</ul>';
            document.getElementById('dm-confirm-summary').innerHTML = summary;

            document.getElementById('dm-step2').style.display = 'none';
            document.getElementById('dm-step3').style.display = '';
        });

        step3Back.addEventListener('click', function() {
            document.getElementById('dm-step3').style.display = 'none';
            document.getElementById('dm-step2').style.display = '';
        });

        deployBtn.addEventListener('click', function() {
            deployBtn.disabled = true;
            deployBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Desplegando...';

            var modules = selectedModules.map(function(m) {
                return { module_id: parseInt(m.id, 10), source_instance_id: m.source.instance_id };
            });

            fetch(DOL_URL_ROOT + '/custom/deploymanager/ajax/deploy_execute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modules: modules,
                    instance_ids: selectedInstances,
                    token: (typeof DM_TOKEN !== 'undefined') ? DM_TOKEN : ''
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.ok) {
                    window.location.href = DOL_URL_ROOT + '/custom/deploymanager/deploy_history.php';
                } else {
                    alert('Error: ' + (result.error || 'Unknown'));
                    deployBtn.disabled = false;
                    deployBtn.innerHTML = '<i class="fa fa-rocket"></i> Desplegar ahora';
                }
            })
            .catch(function() {
                alert('Error de red');
                deployBtn.disabled = false;
                deployBtn.innerHTML = '<i class="fa fa-rocket"></i> Desplegar ahora';
            });
        });
    }

    function initAutodiscover() {
        document.querySelectorAll('.dm-autodiscover').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var serverId = btn.getAttribute('data-server');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Detectando...';

                fetch(DOL_URL_ROOT + '/custom/deploymanager/ajax/autodiscover.php?server_id=' + serverId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.ok) {
                            btn.innerHTML = '<i class="fa fa-check"></i> ' + data.added + ' añadidas, ' + data.skipped + ' existentes';
                            if (data.added > 0) {
                                setTimeout(function() { location.reload(); }, 1500);
                            }
                        } else {
                            btn.innerHTML = '<i class="fa fa-times"></i> Error: ' + (data.error || '');
                            btn.disabled = false;
                        }
                    })
                    .catch(function() {
                        btn.innerHTML = '<i class="fa fa-times"></i> Error de red';
                        btn.disabled = false;
                    });
            });
        });
    }
})();

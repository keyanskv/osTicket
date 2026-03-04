/**
 * Dashboard Custom Tabs - Departments, Help Topics, Agents
 * osTicket 1.18+ Custom Extension
 */

(function ($) {
    'use strict';

    var DashboardTabs = {
        currentTab: null,
        currentItem: null,
        currentItemType: null,

        init: function () {
            this.bindEvents();
            this.loadDepartments();
            this.loadHelpTopics();
            this.loadAgents();
        },

        bindEvents: function () {
            var self = this;

            // Tab switching
            $(document).on('click', '.dashboard-custom-tabs .tab-btn', function (e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                self.switchTab(tab);
            });

            // Item click handlers
            $(document).on('click', '.dept-link', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var name = $(this).text();
                self.loadDepartmentTickets(id, name);
            });

            $(document).on('click', '.topic-link', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var name = $(this).text();
                self.loadTopicTickets(id, name);
            });

            $(document).on('click', '.agent-link', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var name = $(this).text();
                self.loadAgentReplies(id, name);
            });

            // Export handlers
            $(document).on('click', '.export-csv-btn', function (e) {
                e.preventDefault();
                self.exportCSV();
            });

            $(document).on('click', '.export-pdf-btn', function (e) {
                e.preventDefault();
                self.exportPDF();
            });

            // Close results panel
            $(document).on('click', '.close-results-btn', function (e) {
                e.preventDefault();
                self.currentItem = null;
                self.currentItemType = null;
            });

            // Report period switching
            $(document).on('click', '.period-btn', function (e) {
                e.preventDefault();
                var period = $(this).data('period');
                self.loadReport(period);
            });
        },

        switchTab: function (tab) {
            this.currentTab = tab;

            // Update tab buttons
            $('.dashboard-custom-tabs .tab-btn').removeClass('active');
            $('.dashboard-custom-tabs .tab-btn[data-tab="' + tab + '"]').addClass('active');

            // Show/hide content
            $('.tab-content-panel').hide();
            $('#tab-' + tab).show();

            // Clear results when switching tabs
            $('#dashboard-results-panel').hide();
            this.currentItem = null;
            this.currentItemType = null;

            // If switching to report tab, load default report (daily)
            if (tab === 'report') {
                this.loadReport('daily');
            }
        },

        loadDepartments: function () {
            var self = this;
            $.ajax({
                url: 'ajax.php/dashboard/departments',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderDepartmentsList(data.departments);
                },
                error: function (xhr) {
                    console.error('Error loading departments:', xhr.responseText);
                    $('#dept-list').html('<p class="error-msg">Error loading departments</p>');
                }
            });
        },

        renderDepartmentsList: function (departments) {
            var html = '<ul class="item-list">';
            if (departments && departments.length > 0) {
                $.each(departments, function (i, dept) {
                    html += '<li><a href="#" class="dept-link" data-id="' + dept.id + '">' +
                        '<i class="icon-folder-close"></i> ' +
                        DashboardTabs.escapeHtml(dept.name) + '</a></li>';
                });
            } else {
                html += '<li class="no-items">No departments found</li>';
            }
            html += '</ul>';
            $('#dept-list').html(html);
        },

        loadDepartmentTickets: function (deptId, deptName) {
            var self = this;
            this.currentItem = deptId;
            this.currentItemType = 'department';

            this.showLoading('Loading tickets for ' + deptName + '...');

            $.ajax({
                url: 'ajax.php/dashboard/departments/' + deptId + '/tickets',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderDepartmentTickets(data);
                },
                error: function (xhr) {
                    self.showError('Error loading department tickets');
                }
            });
        },

        renderDepartmentTickets: function (data) {
            var html = '<div class="results-header">' +
                '<h3><i class="icon-folder-open"></i> ' + this.escapeHtml(data.department) +
                ' <span class="count">(' + data.count + ' tickets)</span></h3>' +
                '<div class="results-actions">' +
                '<button class="export-csv-btn action-button" title="Export CSV"><i class="icon-download-alt"></i> CSV</button> ' +
                '<button class="export-pdf-btn action-button" title="Export PDF"><i class="icon-file-text"></i> PDF</button> ' +
                '<button class="close-results-btn action-button" title="Close"><i class="icon-remove"></i></button>' +
                '</div></div>';

            if (data.tickets && data.tickets.length > 0) {
                html += '<div class="table-responsive"><table class="results-table">' +
                    '<thead><tr>' +
                    '<th>Ticket #</th>' +
                    '<th>User</th>' +
                    '<th>Assigned Agent</th>' +
                    '<th>Status</th>' +
                    '<th>Subject</th>' +
                    '<th>Created</th>' +
                    '</tr></thead><tbody>';

                $.each(data.tickets, function (i, ticket) {
                    html += '<tr>' +
                        '<td><a href="tickets.php?id=' + ticket.ticket_id + '" class="ticket-link">#' +
                        DashboardTabs.escapeHtml(ticket.ticket_number) + '</a></td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.user) + '</td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.assigned_agent) + '</td>' +
                        '<td><span class="status-badge">' + DashboardTabs.escapeHtml(ticket.status) + '</span></td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.subject) + '</td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.created) + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table></div>';
            } else {
                html += '<p class="no-results">No tickets found for this department</p>';
            }

            this.showResults(html);
        },

        loadHelpTopics: function () {
            var self = this;
            $.ajax({
                url: 'ajax.php/dashboard/topics',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderHelpTopicsList(data.topics);
                },
                error: function (xhr) {
                    console.error('Error loading help topics:', xhr.responseText);
                    $('#topic-list').html('<p class="error-msg">Error loading help topics</p>');
                }
            });
        },

        renderHelpTopicsList: function (topics) {
            var html = '<ul class="item-list">';
            if (topics && topics.length > 0) {
                $.each(topics, function (i, topic) {
                    html += '<li><a href="#" class="topic-link" data-id="' + topic.id + '">' +
                        '<i class="icon-question-sign"></i> ' +
                        DashboardTabs.escapeHtml(topic.name) + '</a></li>';
                });
            } else {
                html += '<li class="no-items">No help topics found</li>';
            }
            html += '</ul>';
            $('#topic-list').html(html);
        },

        loadTopicTickets: function (topicId, topicName) {
            var self = this;
            this.currentItem = topicId;
            this.currentItemType = 'topic';

            this.showLoading('Loading tickets for ' + topicName + '...');

            $.ajax({
                url: 'ajax.php/dashboard/topics/' + topicId + '/tickets',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderTopicTickets(data);
                },
                error: function (xhr) {
                    self.showError('Error loading topic tickets');
                }
            });
        },

        renderTopicTickets: function (data) {
            var html = '<div class="results-header">' +
                '<h3><i class="icon-question-sign"></i> ' + this.escapeHtml(data.topic) +
                ' <span class="count">(' + data.count + ' tickets)</span></h3>' +
                '<div class="results-actions">' +
                '<button class="export-csv-btn action-button" title="Export CSV"><i class="icon-download-alt"></i> CSV</button> ' +
                '<button class="export-pdf-btn action-button" title="Export PDF"><i class="icon-file-text"></i> PDF</button> ' +
                '<button class="close-results-btn action-button" title="Close"><i class="icon-remove"></i></button>' +
                '</div></div>';

            if (data.tickets && data.tickets.length > 0) {
                html += '<div class="table-responsive"><table class="results-table">' +
                    '<thead><tr>' +
                    '<th>Ticket #</th>' +
                    '<th>User</th>' +
                    '<th>Assigned Staff</th>' +
                    '<th>Status</th>' +
                    '<th>Created</th>' +
                    '</tr></thead><tbody>';

                $.each(data.tickets, function (i, ticket) {
                    html += '<tr>' +
                        '<td><a href="tickets.php?id=' + ticket.ticket_id + '" class="ticket-link">#' +
                        DashboardTabs.escapeHtml(ticket.ticket_number) + '</a></td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.user) + '</td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.assigned_staff) + '</td>' +
                        '<td><span class="status-badge">' + DashboardTabs.escapeHtml(ticket.status) + '</span></td>' +
                        '<td>' + DashboardTabs.escapeHtml(ticket.created) + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table></div>';
            } else {
                html += '<p class="no-results">No tickets found for this help topic</p>';
            }

            this.showResults(html);
        },

        loadReport: function (period) {
            var self = this;
            this.currentItem = period;
            this.currentItemType = 'report';

            // Update period buttons
            $('.period-btn').removeClass('active');
            $('.period-btn[data-period="' + period + '"]').addClass('active');

            var html = '<div class="results-loading">' +
                '<i class="icon-spinner icon-spin icon-2x"></i>' +
                '<p>Loading ' + period + ' report...</p></div>';
            $('#report-results').html(html);

            $.ajax({
                url: 'ajax.php/dashboard/report/agent-replies/' + period,
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderReport(data);
                },
                error: function (xhr) {
                    $('#report-results').html('<p class="error-msg">Error loading report</p>');
                }
            });
        },

        renderReport: function (data) {
            var html = '<div class="results-header">' +
                '<h3><i class="icon-bar-chart"></i> ' + this.escapeHtml(data.period) +
                ' <span class="count">(' + data.count + ' agents)</span></h3>' +
                '<div class="results-actions">' +
                '<button class="export-report-csv-btn action-button" title="Export CSV"><i class="icon-download-alt"></i> CSV</button> ' +
                '<button class="export-report-pdf-btn action-button" title="Export PDF"><i class="icon-file-text"></i> PDF</button> ' +
                '</div></div>';

            if (data.report && data.report.length > 0) {
                html += '<div class="table-responsive"><table class="results-table">' +
                    '<thead><tr>' +
                    '<th>Agent</th>' +
                    '<th>Replies</th>' +
                    '<th>Last Reply Date</th>' +
                    '</tr></thead><tbody>';

                $.each(data.report, function (i, item) {
                    html += '<tr>' +
                        '<td>' + DashboardTabs.escapeHtml(item.agent) + '</td>' +
                        '<td>' + DashboardTabs.escapeHtml(item.replies) + '</td>' +
                        '<td>' + DashboardTabs.escapeHtml(item.last_reply) + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table></div>';
            } else {
                html += '<p class="no-results">No agent replies found for this period</p>';
            }

            $('#report-results').html(html);

            // Bind export events for report (since they are dynamic)
            var self = this;
            $('.export-report-csv-btn').off('click').on('click', function () {
                window.location.href = 'ajax.php/dashboard/report/agent-replies/' + self.currentItem + '/export/csv';
            });
            $('.export-report-pdf-btn').off('click').on('click', function () {
                window.location.href = 'ajax.php/dashboard/report/agent-replies/' + self.currentItem + '/export/pdf';
            });
        },

        loadAgents: function () {
            var self = this;
            $.ajax({
                url: 'ajax.php/dashboard/agents',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderAgentsList(data.agents);
                },
                error: function (xhr) {
                    console.error('Error loading agents:', xhr.responseText);
                    $('#agent-list').html('<p class="error-msg">Error loading agents</p>');
                }
            });
        },

        renderAgentsList: function (agents) {
            var html = '<ul class="item-list">';
            if (agents && agents.length > 0) {
                $.each(agents, function (i, agent) {
                    html += '<li><a href="#" class="agent-link" data-id="' + agent.id + '">' +
                        '<i class="icon-user"></i> ' +
                        DashboardTabs.escapeHtml(agent.name) + '</a></li>';
                });
            } else {
                html += '<li class="no-items">No agents found</li>';
            }
            html += '</ul>';
            $('#agent-list').html(html);
        },

        loadAgentReplies: function (agentId, agentName) {
            var self = this;
            this.currentItem = agentId;
            this.currentItemType = 'agent';

            this.showLoading('Loading replies by ' + agentName + '...');

            $.ajax({
                url: 'ajax.php/dashboard/agents/' + agentId + '/replies',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    self.renderAgentReplies(data);
                },
                error: function (xhr) {
                    self.showError('Error loading agent replies');
                }
            });
        },

        renderAgentReplies: function (data) {
            var html = '<div class="results-header">' +
                '<h3><i class="icon-user"></i> ' + this.escapeHtml(data.agent) +
                ' <span class="count">(' + data.count + ' replies)</span></h3>' +
                '<div class="results-actions">' +
                '<button class="export-csv-btn action-button" title="Export CSV"><i class="icon-download-alt"></i> CSV</button> ' +
                '<button class="export-pdf-btn action-button" title="Export PDF"><i class="icon-file-text"></i> PDF</button> ' +
                '<button class="close-results-btn action-button" title="Close"><i class="icon-remove"></i></button>' +
                '</div></div>';

            if (data.replies && data.replies.length > 0) {
                html += `
                    <div class="table-responsive">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Entry ID</th>
                                <th>Ticket #</th>
                                <th>Agent</th>
                                <th>Subject</th>
                                <th>Reply Message</th>
                                <th>Ticket Creation Date</th>
                                <th>Reply Date</th>
                            </tr>
                        </thead>
                        <tbody>`;

                $.each(data.replies, function (i, reply) {
                    var ticketLink = reply.ticket_id ?
                        `<a href="tickets.php?id=${reply.ticket_id}" class="ticket-link">#${DashboardTabs.escapeHtml(reply.ticket_number)}</a>` :
                        (DashboardTabs.escapeHtml(reply.ticket_number) || '-');

                    html += `
                        <tr>
                            <td>${DashboardTabs.escapeHtml(reply.entry_id)}</td>
                            <td>${ticketLink}</td>
                            <td>${DashboardTabs.escapeHtml(reply.agent_name)}</td>
                            <td>${DashboardTabs.escapeHtml(reply.ticket_subject)}</td>
                            <td class="reply-message">${DashboardTabs.escapeHtml(reply.reply_message)}</td>
                            <td>${DashboardTabs.escapeHtml(reply.ticket_created)}</td>
                            <td>${DashboardTabs.escapeHtml(reply.reply_date)}</td>
                        </tr>`;
                });

                html += `</tbody></table></div>`;
            } else {
                html += '<p class="no-results">No replies found for this agent</p>';
            }

            this.showResults(html);
        },

        exportCSV: function () {
            if (!this.currentItem || !this.currentItemType) {
                alert('Please select an item first');
                return;
            }

            var url = 'ajax.php/dashboard/';
            switch (this.currentItemType) {
                case 'department':
                    url += 'departments/' + this.currentItem + '/export/csv';
                    break;
                case 'topic':
                    url += 'topics/' + this.currentItem + '/export/csv';
                    break;
                case 'agent':
                    url += 'agents/' + this.currentItem + '/export/csv';
                    break;
            }

            window.location.href = url;
        },

        exportPDF: function () {
            if (!this.currentItem || !this.currentItemType) {
                alert('Please select an item first');
                return;
            }

            var url = 'ajax.php/dashboard/';
            switch (this.currentItemType) {
                case 'department':
                    url += 'departments/' + this.currentItem + '/export/pdf';
                    break;
                case 'topic':
                    url += 'topics/' + this.currentItem + '/export/pdf';
                    break;
                case 'agent':
                    url += 'agents/' + this.currentItem + '/export/pdf';
                    break;
            }

            window.location.href = url;
        },

        showLoading: function (message) {
            var html = '<div class="results-loading">' +
                '<i class="icon-spinner icon-spin icon-2x"></i>' +
                '<p>' + this.escapeHtml(message) + '</p></div>';

            $('#dashboard-results-content').html(html);
            $('#dashboard-results-panel').slideDown();
        },

        showResults: function (html) {
            $('#dashboard-results-content').html(html);
            $('#dashboard-results-panel').slideDown();

            // Scroll to results
            $('html, body').animate({
                scrollTop: $('#dashboard-results-panel').offset().top - 20
            }, 300);
        },

        showError: function (message) {
            var html = '<div class="results-error">' +
                '<i class="icon-warning-sign"></i> ' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button class="close-results-btn action-button">Close</button></div>';

            $('#dashboard-results-content').html(html);
            $('#dashboard-results-panel').slideDown();
        },

        escapeHtml: function (text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Make it accessible globally
    window.DashboardTabs = DashboardTabs;

    // Initialize on document ready
    $(document).ready(function () {
        if ($('#dashboard-custom-tabs-container').length) {
            DashboardTabs.init();
        }
    });

})(jQuery);


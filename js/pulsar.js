function getGLPICSRFToken() {
    var metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }

    var inputToken = document.querySelector('input[name="_glpi_csrf_token"]');
    if (inputToken) {
        return inputToken.value;
    }

    return '';
}

// ========================================
// PULSAR LIKE (Com reload)
// ========================================

var PulsarLike = {
    toggle: function(ticketId, callback) {
        var token = getGLPICSRFToken();

        var payload = new URLSearchParams();
        payload.append('action', 'toggle');
        payload.append('ticket_id', ticketId);

        if (token) {
            payload.append('_glpi_csrf_token', token);
        }

        var ajaxUrl = '/plugins/ideas/front/like.php';

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload.toString()
        })
        .then(function(response) {
            setTimeout(function() {
                window.location.reload();
            }, 100);
            
            return response.text();
        })
        .then(function(text) {
            // Vazio - ignora resposta
        })
        .catch(function(error) {
            console.error('💥 ERRO na requisição:', error);
            setTimeout(function() {
                window.location.reload();
            }, 100);
        });
    }
};

// ========================================
// PULSAR COMMENT (Com reload)
// ========================================

var PulsarComment = {
    add: function(ticketId, content, callback) {
        var token = getGLPICSRFToken();
        
        var formData = new URLSearchParams();
        formData.append('ticket_id', ticketId);
        formData.append('content', content);
        
        if (token) {
            formData.append('_glpi_csrf_token', token);
        }

        var ajaxUrl = '/plugins/ideas/front/add_comment.php';

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(function(response) {
            setTimeout(function() {
                window.location.reload();
            }, 300);
        })
        .catch(function(error) {
            console.error('💥 ERRO:', error);
            alert('Erro ao adicionar comentário');
        });
    }
};

// ========================================
// PULSAR RANKING
// ========================================

var PulsarRanking = {
    get: function(period, limit, callback) {
        var token = encodeURIComponent(getGLPICSRFToken());
        fetch('../ajax/ranking.php?period=' + period + '&limit=' + limit + '&_glpi_csrf_token=' + token)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (callback) callback(data);
        })
        .catch(function(error) {
            console.error('Error:', error);
            if (callback) callback({success: false, message: 'Erro ao buscar ranking'});
        });
    }
};

// ========================================
// PULSAR UTILS
// ========================================

var PulsarUtils = {
    copyToClipboard: function(text, callback) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                if (callback) callback(true);
            }).catch(function(err) {
                console.error('Erro ao copiar:', err);
                if (callback) callback(false);
            });
        } else {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                if (callback) callback(true);
            } catch (err) {
                console.error('Erro ao copiar:', err);
                if (callback) callback(false);
            }
            document.body.removeChild(textArea);
        }
    },

    timeAgo: function(dateString) {
        var date = new Date(dateString);
        var now = new Date();
        var seconds = Math.floor((now - date) / 1000);
        
        var intervals = {
            'ano': 31536000,
            'mês': 2592000,
            'semana': 604800,
            'dia': 86400,
            'hora': 3600,
            'minuto': 60
        };
        
        for (var key in intervals) {
            var interval = Math.floor(seconds / intervals[key]);
            if (interval >= 1) {
                return interval + ' ' + key + (interval > 1 ? 's' : '') + ' atrás';
            }
        }
        
        return 'agora';
    },

    scrollToElement: function(selector, offset) {
        offset = offset || 0;
        var element = document.querySelector(selector);
        if (element) {
            var elementPosition = element.getBoundingClientRect().top;
            var offsetPosition = elementPosition + window.pageYOffset - offset;
            
            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    },

    debounce: function(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// ========================================
// PULSAR CAMPAIGN (COM AJAX)
// ========================================

var PulsarCampaign = {
    _ajaxUrl: null,
    _cachedCampaigns: null,

    _getAjaxUrl: function() {
        if (this._ajaxUrl) {
            return this._ajaxUrl;
        }

        this._ajaxUrl = window.location.pathname.replace(/\/front\/.*/, '') + '/front/link_campaign.php';
        return this._ajaxUrl;
    },

    _safeParseResponse: function(text) {
        if (!text) {
            return null;
        }

        var trimmed = text.trim();

        if (!trimmed) {
            return null;
        }

        try {
            return JSON.parse(trimmed);
        } catch (error) {
            var start = trimmed.indexOf('{');
            var end = trimmed.lastIndexOf('}');

            if (start === -1 || end === -1 || end < start) {
                start = trimmed.indexOf('[');
                end = trimmed.lastIndexOf(']');
            }

            if (start !== -1 && end !== -1 && end >= start) {
                try {
                    return JSON.parse(trimmed.slice(start, end + 1));
                } catch (ignored) {
                    // mantém fluxo de erro
                }
            }

            return null;
        }
    },

    // ✅ Buscar campanhas (simplificado)
    getCampaigns: function(callback) {
        var token = getGLPICSRFToken();
        var payload = new URLSearchParams();
        payload.append('action', 'get_campaigns');

        if (token) {
            payload.append('_glpi_csrf_token', token);
        }

        var ajaxUrl = this._getAjaxUrl();
        var self = this;

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload.toString()
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(text) {
            var data = self._safeParseResponse(text);

            if (data && typeof data === 'object' && data.success) {
                self._cachedCampaigns = Array.isArray(data.campaigns) ? data.campaigns : [];

                if (typeof callback === 'function') {
                    callback({
                        success: true,
                        campaigns: self._cachedCampaigns
                    });
                }
                return;
            }

            if (self._cachedCampaigns) {
                console.warn('Falha ao interpretar resposta das campanhas, utilizando cache.', text);

                if (typeof callback === 'function') {
                    callback({
                        success: true,
                        campaigns: self._cachedCampaigns,
                        fromCache: true
                    });
                }
                return;
            }

            if (typeof callback === 'function') {
                callback({
                    success: false,
                    message: 'Não foi possível interpretar a lista de campanhas.'
                });
            }
        })
        .catch(function(error) {
            console.error('Erro ao buscar campanhas:', error);
            if (self._cachedCampaigns && typeof callback === 'function') {
                callback({
                    success: true,
                    campaigns: self._cachedCampaigns,
                    fromCache: true
                });
                return;
            }

            if (callback) {
                callback({
                    success: false,
                    message: 'Erro ao carregar campanhas'
                });
            }
        });
    },

    // ✅ Vincular
    link: function(ticketId, campaignId) {
        var token = getGLPICSRFToken();

        var payload = new URLSearchParams();
        payload.append('action', 'link');
        payload.append('ticket_id', ticketId);
        payload.append('campaign_id', campaignId);

        if (token) {
            payload.append('_glpi_csrf_token', token);
        }

        var ajaxUrl = this._getAjaxUrl();

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload.toString()
        })
        .then(function(response) {
            setTimeout(function() {
                window.location.reload();
            }, 150);

            return response.text();
        })
        .then(function() {
            // Ignora o corpo - mensagens são exibidas após o reload
        })
        .catch(function(error) {
            console.error('Erro ao vincular campanha:', error);
            setTimeout(function() {
                window.location.reload();
            }, 300);
        });
    },

    // ✅ Desvincular
    unlink: function(ticketId) {
        if (!confirm('Deseja realmente desvincular esta ideia da campanha?')) {
            return;
        }

        var token = getGLPICSRFToken();
        var payload = new URLSearchParams();
        payload.append('action', 'unlink');
        payload.append('ticket_id', ticketId);

        if (token) {
            payload.append('_glpi_csrf_token', token);
        }

        var ajaxUrl = this._getAjaxUrl();

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload.toString()
        })
        .then(function(response) {
            setTimeout(function() {
                window.location.reload();
            }, 150);

            return response.text();
        })
        .then(function() {
            // Ignora o corpo - mensagens são exibidas após o reload
        })
        .catch(function(error) {
            console.error('Erro ao desvincular campanha:', error);
            setTimeout(function() {
                window.location.reload();
            }, 300);
        });
    },

    // ✅ Abrir modal
    openModal: function(ticketId, currentCampaignId) {
        var self = this;
        
        var existingModal = document.getElementById('campaign-link-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        this.getCampaigns(function(response) {
            if (!response.success) {
                alert(response.message || 'Não foi possível carregar as campanhas.');
                return;
            }

            self.createModal(ticketId, Array.isArray(response.campaigns) ? response.campaigns : [], currentCampaignId);
        });
    },

    // ✅ Criar modal
    createModal: function(ticketId, campaigns, currentCampaignId) {
        var existingModal = document.getElementById('campaign-link-modal');
        if (existingModal) {
            existingModal.remove();
        }

        var modal = document.createElement('div');
        modal.id = 'campaign-link-modal';
        modal.className = 'pulsar-modal';

        var escapeHtml = function(text) {
            if (text === null || text === undefined) {
                return '';
            }

            return String(text).replace(/[&<>"']/g, function(char) {
                switch (char) {
                    case '&': return '&amp;';
                    case '<': return '&lt;';
                    case '>': return '&gt;';
                    case '"': return '&quot;';
                    case "'": return '&#39;';
                    default: return char;
                }
            });
        };

        var campaignsHTML = '';
        if (!campaigns.length) {
            campaignsHTML = '<p class="empty-message">Nenhuma campanha ativa encontrada.</p>';
        } else {
            campaigns.forEach(function(campaign) {
                var selected = String(campaign.id) === String(currentCampaignId);
                var deadline = campaign.deadline || 'Sem prazo definido';
                campaignsHTML += '<div class="campaign-option' + (selected ? ' selected' : '') + '" data-campaign-id="' + campaign.id + '">';
                campaignsHTML += '<div class="campaign-option-info">';
                campaignsHTML += '<h4>' + escapeHtml(campaign.name) + '</h4>';
                campaignsHTML += '<p class="campaign-deadline"><i class="fa-solid fa-calendar"></i> Prazo: ' + escapeHtml(deadline) + '</p>';
                campaignsHTML += '<p class="campaign-status"><i class="fa-solid fa-circle-info"></i> ' + escapeHtml(campaign.status || '') + '</p>';
                campaignsHTML += '</div>';
                campaignsHTML += '<button class="btn-select" data-campaign-id="' + campaign.id + '">' + (selected ? 'Selecionada' : 'Selecionar') + '</button>';
                campaignsHTML += '</div>';
            });
        }

        modal.innerHTML = '<div class="modal-overlay"></div>';
        modal.innerHTML += '<div class="modal-content">';
        modal.innerHTML += '<div class="modal-header">';
        modal.innerHTML += '<button class="modal-close"><i class="fa-solid fa-times"></i></button>';
        modal.innerHTML += '</div>';
        modal.innerHTML += '<div class="modal-body">' + campaignsHTML + '</div>';
        modal.innerHTML += '<div class="modal-footer">';
        if (currentCampaignId) {
            modal.innerHTML += '<button class="btn-unlink">Desvincular Campanha</button>';
        }
        modal.innerHTML += '</div>';
        modal.innerHTML += '</div>';

        document.body.appendChild(modal);

        var closeModal = function() {
            modal.classList.remove('show');
            setTimeout(function() {
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            }, 300);
        };

        var overlayElement = modal.querySelector('.modal-overlay');
        if (overlayElement) {
            overlayElement.addEventListener('click', closeModal);
        }

        var closeButton = modal.querySelector('.modal-close');
        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        }

        modal.querySelectorAll('.btn-select').forEach(function(button) {
            button.addEventListener('click', function() {
                var campaignId = this.getAttribute('data-campaign-id');
                closeModal();
                PulsarCampaign.link(ticketId, campaignId);
            });
        });

        var unlinkButton = modal.querySelector('.btn-unlink');
        if (unlinkButton) {
            unlinkButton.addEventListener('click', function() {
                closeModal();
                PulsarCampaign.unlink(ticketId);
            });
        }

        setTimeout(function() {
            modal.classList.add('show');
        }, 10);
    }
};

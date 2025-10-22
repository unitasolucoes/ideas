/**
 * Form Helpers - Funções auxiliares para formulários do plugin Ideas
 * Inspirado no plugin Reembolso, mas com melhorias modernas
 */

(function (window) {
  'use strict';

  const FormHelpers = {};

  /**
   * Constantes de validação
   */
  FormHelpers.MAX_FILE_SIZE = 104857600; // 100 MB
  FormHelpers.ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

  /**
   * Formata bytes para formato legível (KB, MB, GB)
   */
  FormHelpers.formatBytes = function (bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  };

  /**
   * Valida extensão do arquivo
   */
  FormHelpers.isValidExtension = function (filename) {
    const extension = filename.split('.').pop().toLowerCase();
    return FormHelpers.ALLOWED_EXTENSIONS.includes(extension);
  };

  /**
   * Valida tamanho do arquivo
   */
  FormHelpers.isValidSize = function (size) {
    return size <= FormHelpers.MAX_FILE_SIZE;
  };

  /**
   * Cria preview de arquivos selecionados
   */
  FormHelpers.createFilePreview = function (fileInput, containerId) {
    const container = document.getElementById(containerId);
    if (!container || !fileInput) {
      return;
    }

    const files = Array.from(fileInput.files);
    if (files.length === 0) {
      container.innerHTML = '';
      container.style.display = 'none';
      return;
    }

    let html = '<div class="file-preview-list">';
    html += '<h4 class="file-preview-title">Arquivos selecionados (' + files.length + '):</h4>';
    html += '<ul class="file-preview-items">';

    files.forEach((file, index) => {
      const isValid = FormHelpers.isValidExtension(file.name) && FormHelpers.isValidSize(file.size);
      const statusClass = isValid ? 'valid' : 'invalid';
      const statusIcon = isValid ? '✓' : '✗';
      const statusText = !FormHelpers.isValidExtension(file.name)
        ? 'Extensão não permitida'
        : !FormHelpers.isValidSize(file.size)
        ? 'Arquivo muito grande (máx. ' + FormHelpers.formatBytes(FormHelpers.MAX_FILE_SIZE) + ')'
        : 'OK';

      html += '<li class="file-preview-item file-preview-item--' + statusClass + '" data-index="' + index + '">';
      html += '  <span class="file-preview-icon">' + statusIcon + '</span>';
      html += '  <div class="file-preview-info">';
      html += '    <span class="file-preview-name">' + FormHelpers.escapeHtml(file.name) + '</span>';
      html += '    <span class="file-preview-meta">' + FormHelpers.formatBytes(file.size) + ' - ' + statusText + '</span>';
      html += '  </div>';
      html += '  <button type="button" class="file-preview-remove" data-index="' + index + '" title="Remover arquivo">';
      html += '    <i class="fa-solid fa-times"></i>';
      html += '  </button>';
      html += '</li>';
    });

    html += '</ul>';

    const invalidCount = files.filter(f => !FormHelpers.isValidExtension(f.name) || !FormHelpers.isValidSize(f.size)).length;
    if (invalidCount > 0) {
      html += '<div class="file-preview-warning">';
      html += '  <i class="fa-solid fa-exclamation-triangle"></i> ';
      html += '  ' + invalidCount + ' arquivo(s) não será(ão) enviado(s) por não atender aos critérios.';
      html += '</div>';
    }

    html += '</div>';

    container.innerHTML = html;
    container.style.display = 'block';

    // Adicionar event listeners para botões de remoção
    container.querySelectorAll('.file-preview-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const indexToRemove = parseInt(this.getAttribute('data-index'));
        FormHelpers.removeFileFromInput(fileInput, indexToRemove, containerId);
      });
    });
  };

  /**
   * Remove arquivo específico do input file
   */
  FormHelpers.removeFileFromInput = function (fileInput, indexToRemove, containerId) {
    const dt = new DataTransfer();
    const files = Array.from(fileInput.files);

    files.forEach(function (file, index) {
      if (index !== indexToRemove) {
        dt.items.add(file);
      }
    });

    fileInput.files = dt.files;
    FormHelpers.createFilePreview(fileInput, containerId);
  };

  /**
   * Valida todos os arquivos do input
   */
  FormHelpers.validateFiles = function (fileInput) {
    const files = Array.from(fileInput.files);
    const invalid = [];

    files.forEach(function (file) {
      if (!FormHelpers.isValidExtension(file.name)) {
        invalid.push({ file: file.name, reason: 'Extensão não permitida' });
      } else if (!FormHelpers.isValidSize(file.size)) {
        invalid.push({
          file: file.name,
          reason: 'Tamanho máximo de ' + FormHelpers.formatBytes(FormHelpers.MAX_FILE_SIZE)
        });
      }
    });

    return {
      isValid: invalid.length === 0,
      errors: invalid,
      validCount: files.length - invalid.length,
      invalidCount: invalid.length
    };
  };

  /**
   * Adiciona contador de caracteres em um textarea
   */
  FormHelpers.addCharCounter = function (textareaId, maxChars, containerId) {
    const textarea = document.getElementById(textareaId);
    const container = containerId ? document.getElementById(containerId) : null;

    if (!textarea) {
      return;
    }

    const counterId = textareaId + '-char-count';
    let counterEl = document.getElementById(counterId);

    if (!counterEl) {
      counterEl = document.createElement('div');
      counterEl.id = counterId;
      counterEl.className = 'char-counter';

      if (container) {
        container.appendChild(counterEl);
      } else if (textarea.parentNode) {
        textarea.parentNode.appendChild(counterEl);
      }
    }

    const updateCounter = function () {
      const length = textarea.value.length;
      const remaining = maxChars ? maxChars - length : length;
      const percentage = maxChars ? (length / maxChars) * 100 : 0;

      if (maxChars) {
        counterEl.textContent = remaining + ' caracteres restantes';
        counterEl.className = 'char-counter ' +
          (percentage > 90 ? 'char-counter--danger' :
           percentage > 75 ? 'char-counter--warning' :
           'char-counter--ok');
      } else {
        counterEl.textContent = length + ' caracteres';
        counterEl.className = 'char-counter char-counter--info';
      }
    };

    textarea.addEventListener('input', updateCounter);
    textarea.addEventListener('keyup', updateCounter);
    updateCounter();
  };

  /**
   * Inicializa contadores para todos os textareas do formulário
   */
  FormHelpers.initCharCounters = function (formId, configs) {
    configs.forEach(function (config) {
      FormHelpers.addCharCounter(config.id, config.max, config.containerId);
    });
  };

  /**
   * Escapa HTML para prevenir XSS
   */
  FormHelpers.escapeHtml = function (text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
  };

  /**
   * Mostra mensagem de erro com estilo
   */
  FormHelpers.showError = function (message, title) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: title || 'Erro',
        text: message,
        confirmButtonText: 'OK'
      });
    } else {
      alert((title ? title + '\n\n' : '') + message);
    }
  };

  /**
   * Mostra mensagem de sucesso com estilo
   */
  FormHelpers.showSuccess = function (message, title) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'success',
        title: title || 'Sucesso',
        text: message,
        confirmButtonText: 'OK'
      });
    } else {
      alert((title ? title + '\n\n' : '') + message);
    }
  };

  /**
   * Mostra confirmação com estilo
   */
  FormHelpers.showConfirm = function (message, title, callback) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'question',
        title: title || 'Confirmação',
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Sim',
        cancelButtonText: 'Não'
      }).then(function (result) {
        if (callback) {
          callback(result.isConfirmed);
        }
      });
    } else {
      const result = confirm((title ? title + '\n\n' : '') + message);
      if (callback) {
        callback(result);
      }
    }
  };

  /**
   * Adiciona classe de validação visual nos campos
   */
  FormHelpers.markFieldValid = function (fieldId, isValid, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    field.classList.remove('is-valid', 'is-invalid');
    field.classList.add(isValid ? 'is-valid' : 'is-invalid');

    if (message) {
      let feedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
      if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = isValid ? 'valid-feedback' : 'invalid-feedback';
        field.parentNode.appendChild(feedback);
      }
      feedback.className = isValid ? 'valid-feedback' : 'invalid-feedback';
      feedback.textContent = message;
      feedback.style.display = 'block';
    }
  };

  /**
   * Remove classes de validação de todos os campos
   */
  FormHelpers.clearValidation = function (formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.querySelectorAll('.is-valid, .is-invalid').forEach(function (el) {
      el.classList.remove('is-valid', 'is-invalid');
    });

    form.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(function (el) {
      el.style.display = 'none';
    });
  };

  /**
   * Debounce function para evitar chamadas excessivas
   */
  FormHelpers.debounce = function (func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = function () {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  };

  // Exportar para o objeto global
  window.FormHelpers = FormHelpers;

})(window);

(function(window, $, undefined) {
"use strict";

function setWorking(html) {
	if((typeof(html) == 'string') || (html === true)) {
		$('#working-text').html(((html === true) || (!html.length)) ? 'Working... Please wait' : html);
		$('#working').show().focus();
	}
	else {
		$('#working').hide();
	}
}

function setInputText(input, text) {
	try {
		var p0 = input.selectionStart, p1 = input.selectionEnd;
		if((typeof(p0) === 'number') && (typeof(p1) === 'number')) {
			input.value = input.value.substring(0, p0) + text + input.value.substring(p1, input.value.length);
			input.selectionEnd = input.selectionStart = p0 + text.length;
			return true;
		}
	}
	catch(e) {
	}
	return false;
}

function process(action, data, post, callback) {
	var params = {
		async: true,
		cache: false,
		url: 'pagkages-translations.process.php?action=' + action,
		dataType: 'json'
	};
	if(data) {
		if(data instanceof FormData) {
			params.type = 'POST';
			params.contentType = false;
			params.processData = false;
		}
		else {
			params.type = post ? 'POST' : 'GET';
		}
		params.data = data;
	}
	$.ajax(params)
		.done(function(result) {
			if(result === null) {
				callback(false, 'No response from server');
			}
			else {
				callback(true, result);
			}
		})
		.fail(function(xhr) {
			var msg = '?';
			try {
				if(!xhr.getResponseHeader('Content-Type').indexOf('text/plain')) {
					msg = xhr.responseText;
				}
				else if(xhr.status === 200) {
					msg = 'Internal error';
				}
				else {
					msg = xhr.status + ': ' + xhr.statusText;
				}
			}
			catch(e) {
			}
			callback(false, msg);
		})
	;
}

function Package(d) {
	Package.all.push(this);
	$('#packages-list tbody').append(this.$row = $('<tr />'));
	this.setData(d);
}
Package.all = [];
Package.create = function() {
	Package.edit(null);
};
Package.edit = function(pkg) {
	Package.edit.current = pkg;
	$('#package-handle').val(pkg ? pkg.pHandle : '');
	if(pkg && pkg.pNameTX) {
		$('#package-handle').attr('readonly', true);
	}
	else {
		$('#package-handle').removeAttr('readonly', true);
	}
	$('#package-name').val(pkg ? pkg.getName() : '');
	$('#package-sourceurl').val(pkg ? pkg.pSourceUrl : '');
	if(pkg) {
		if(pkg.pNameDB) {
			if(pkg.pDisabled) {
				$('#package-in-db-yes-disabled').prop('checked', true);
			}
			else {
				$('#package-in-db-yes').prop('checked', true);
			}
		}
		else {
			$('#package-in-db-no').prop('checked', true);
		}
		$('#package-in-tx').prop('checked', pkg.pNameTX ? true : false);
	}
	else {
		$('#package-in-db-yes').prop('checked', true);
		$('#package-in-tx').prop('checked', true);
	}
	Package.edit.updateStatus();
	$('#modal-package').modal('show');
};
Package.edit.updateStatus = function(speed) {
	if(Package.edit.current && Package.edit.current.pNameTX) {
		if($('#package-in-tx').is(':checked')) {
			$('#package-potfile')
				.removeAttr('required')
				.closest('.form-group')
					.show(speed)
			;
			$('label[for="package-potfile"]').text('Update .pot file');
		}
		else {
			$('#package-potfile')
				.removeAttr('required')
				.closest('.form-group')
					.hide(speed)
			;
		}
	}
	else {
		if($('#package-in-tx').is(':checked')) {
			$('label[for="package-potfile"]').text('Initial .pot file');
			$('#package-potfile')
				.attr('required', true)
				.closest('.form-group').show(speed)
			;
		}
		else {
			$('#package-potfile')
				.removeAttr('required')
				.closest('.form-group')
					.hide(speed)
			;
		}
	}
	if($('#package-in-db-no').is(':checked')) {
		$('#package-sourceurl')
			.closest('.form-group').hide(speed)
		;
	}
	else {
		$('#package-sourceurl')
			.closest('.form-group').show(speed)
		;
	}
	if($('#package-in-tx').is(':checked') || (!$('#package-in-db-no').is(':checked'))) {
		$('#package-name, #package-handle')
			.attr('required', true)
			.closest('.form-group').show(speed)
		;
		$('#package-save')
			.removeClass('btn-danger')
			.addClass('btn-primary')
			.text(Package.edit.current ? 'Update' : 'Create')
			.show(speed)
		;
	}
	else {
		$('#package-name, #package-handle')
			.removeAttr('required')
			.closest('.form-group').hide(speed)
		;
		if(Package.edit.current) {
			$('#package-save')
				.removeClass('btn-primary')
				.addClass('btn-danger')
				.text('Delete')
				.show(speed)
			;
		}
		else {
			$('#package-save').hide(speed);
		}
	}
	if(Package.edit.current && Package.edit.current.pNameTX) {
		$('#package-txview').show();
	}
	else {
		$('#package-txview').hide();
	}
};
Package.edit.save = function() {
	var pkg = Package.edit.current, send = new FormData(), v;
	if(pkg) {
		send.append('handleOld', pkg.pHandle);
	}
	var inDB = parseInt($('[name="package-in-db"]:checked').val());
	send.append('inDB', inDB.toString());
	var inTX = $('#package-in-tx').is(':checked');
	send.append('inTX', inTX ? '1' : '0');
	if(inDB || inTX) {
		if(pkg && pkg.pNameTX) {
			v = pkg.pHandle;
		}
		else {
			v = $.trim($('#package-handle').val());
			if(!v) {
				$('#package-handle').val('').focus();
				return;
			}
		}
		send.append('handle', v);
		v = $.trim($('#package-name').val());
		if(!v) {
			$('#package-name').val('').focus();
			return;
		}
		send.append('name', v);
		if(inDB) {
			send.append('sourceurl', $.trim($('#package-sourceurl').val()));
		}
		if(inTX) {
			v = $('#package-potfile');
			if(v.val()) {
				send.append('potfile', v[0].files[0]);
			}
			else if(!(pkg && pkg.pNameTX)) {
				$('#package-potfile').focus();
				return;
			}
		}
	}
	if((inDB === 0) && (!inTX)) {
		if(!pkg) {
			return;
		}
		if(!window.confirm('Are you sure you want to delete the package "' + (pkg.getName()) + '"?')) {
			return;
		}
	}
	else if((inDB === 1) && (!inTX)) {
		if(!window.confirm('The package should be active in the database if and only if it\'s on Transifex.\n\nAre you sure you want to proceed?')) {
			return;
		}
	}
	else if((inDB !== 1) && inTX) {
		if(!window.confirm('All the Transifex resources should be saved (active) in the database.\n\nAre you sure you want to proceed?')) {
			return;
		}
	}
	if((!inTX) && pkg && pkg.pNameTX) {
		if(!window.confirm('WARNING: all translations will be lost!\n\nAre you sure you want to proceed?')) {
			return;
		}
	}
	setWorking('Saving...');
	process('save-package', send, true, function(ok, result) {
		setWorking();
		if(!ok) {
			window.alert(result);
			return;
		}
		$('#modal-package').modal('hide');
		if((inDB === 0) && (!inTX)) {
			pkg.deleted();
		}
		else {
			if(pkg) {
				pkg.setData(result);
			}
			else {
				new Package(result);
			}
		}
	});
};
Package.reload = function() {
	setWorking('Loading packages...');
	$('#packages').hide();
	$('#packages-list tbody').empty();
	Package.all = [];
	process('get-packages', null, false, function(ok, result) {
		setWorking();
		if(!ok) {
			window.alert(result);
			return;
		}
		$.each(result, function() {
			new Package(this);
		});
		$('#packages').show();
	});
};
Package.prototype = {
	setData: function(d) {
		$.extend(true, this, d);
		this.refresh();
	},
	getName: function() {
		return this.pNameTX || this.pNameDB;
	},
	refresh: function() {
		var me = this;
		me.$row
			.empty()
			.append($('<td />')
				.append($('<a href="javascript:void(0)" />')
					.text(this.getName())
					.on('click', function() {
						me.edit();
					})
				)
			)
			.append($('<td />')
				.html(this.pNameDB ? ('<span style="color: green' + (this.pDisabled ? '; opacity: 0.3' : '') + '">&#x2713;</span>') : '<span style="color: red">&#x2717;</span>')
			)
			.append($('<td />')
				.html(this.pNameTX ? '<span style="color: green">&#x2713;</span>' : '<span style="color: red">&#x2717;</span>')
			)
		;
	},
	edit: function() {
		Package.edit(this);
	},
	deleted: function() {
		var me = this;
		me.$row.remove();
		$.each(Package.all, function(i) {
			if(me === this) {
				Package.all.splice(i, 1);
				return false;
			}
		});
	}
};

function loginChanged() {
	$('#my-name').text(C5TT.me ? C5TT.me.uName : '');
	if(C5TT.me) {
		$('.loggedin-no').hide();
		$('#transifex-resources-url').closest('li').hide();
		$('.loggedin-yes').show();
		process('get-transifex-project', null, false, function(ok, result) {
			if(ok) {
				$('#transifex-resources-url')
					.attr('href', 'https://www.transifex.com/projects/p/' + result + '/resources/')
					.closest('li')
						.show()
				;
			}
		});
		Package.reload();
	}
	else {
		$('.loggedin-yes').hide();
		$('.loggedin-no').show();
		if($('#login-username').val().length) {
			$('#login-password').focus();
		}
		else {
			$('#login-username').focus();
		}
	}
}

$(window.document).ready(function() {
	$('.with-tooltip').tooltip({html: true});
	if(!window.FormData) {
		setWorking();
		window.alert('Unsupported browser.');
		return;
	}
	$('#login form').on('submit', function(e) {
		e.preventDefault();
		var send = {username: $.trim($('#login-username').val()), password: $('#login-password').val()};
		if(!send.username.length) {
			$('#login-username').focus();
			return;
		}
		if(!send.username.length) {
			$('#login-password').focus();
			return;
		}
		setWorking('Logging you in...');
		process('login', send, true, function(ok, result) {
			setWorking();
			if(!ok) {
				window.alert(result);
				$('#login-username').focus();
				return;
			}
			$('#login-password').val('');
			C5TT.me = result;
			loginChanged();
		});
	});

	$('#logout').on('click', function() {
		setWorking('Closing session...');
		process('logout', null, false, function(ok, result) {
			setWorking();
			if(!ok) {
				window.alert(result);
				return;
			}
			C5TT.me = null;
			loginChanged();
		});
	});

	$('#packages-reload').on('click', function() {
		Package.reload();
	});
	$('#package-create').on('click', function() {
		Package.create();
	});
	$('#package-in-tx').on('change', function() {
		Package.edit.updateStatus('fast');
	});
	$('input[name="package-in-db"]').on('change', function() {
		Package.edit.updateStatus('fast');
	});
	$('#package-txview').on('click', function() {
		if(Package.edit.current && Package.edit.current.pNameTX) {
			var $a;
			$(window.document.body).append($a = $('<a target="_blank"/> ')
				.attr('href', 'https://www.transifex.com/projects/p/concrete5-packages/resource/' + Package.edit.current.pHandle + '/')
			);
			$a[0].click();
			$a.remove();
		}
	});
	$('#modal-package').on('shown.bs.modal', function () {
		var $potfile = $('#package-potfile');
		$potfile.replaceWith($potfile.clone(true));
		if($('#package-handle').is('[readonly]')) {
			$('#package-name').focus();
		}
		else {
			$('#package-handle').focus();
		}
	});
	$('#modal-package form').on('submit', function(e) {
		e.preventDefault();
		Package.edit.save();
	});
	$('#package-handle')
		.on('keypress', function(e) {
			if(!$(this).is('[readonly]')) {
				var s = String.fromCharCode(e.charCode);
				switch(s) {
					case '_':
					case ' ':
					case '\t'
						if(setInputText(this, '-')) {
							e.preventDefault();
						}
						break;
					default:
						var lc = s.toLowerCase();
						if(s !== lc) {
							if(setInputText(this, lc)) {
								e.preventDefault();
							}
						}
						break;
				}
			}
		})
		.on('blur', function() {
			if(!$(this).is('[readonly]')) {
				this.value = $.trim(this.value).replace(/\s+/g, '-').toLowerCase();
			}
		})
	;
	setWorking();
	loginChanged();
});

})(window, jQuery);

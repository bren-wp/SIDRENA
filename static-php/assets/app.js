(function(){
	'use strict';
	var search=document.getElementById('sid-search');
	var tabs=document.querySelectorAll('[data-tab]');
	var panels=document.querySelectorAll('[data-panel]');

	function fold(value){
		value=(value||'').toLocaleLowerCase('hr');
		return typeof value.normalize==='function'
			? value.normalize('NFD').replace(/[\u0300-\u036f]/g,'')
			: value;
	}

	function filter(){
		var q=fold(search?search.value:'').trim();
		panels.forEach(function(panel){
			var visible=0;
			panel.querySelectorAll('tbody tr[data-search]').forEach(function(row){
				var show=!q||fold(row.getAttribute('data-search')).indexOf(q)!==-1;
				row.hidden=!show;
				if(show){visible++;}
			});
			var empty=panel.querySelector('.sid-no-results');
			if(empty){empty.hidden=visible!==0;}
		});
	}

	tabs.forEach(function(tab){
		tab.addEventListener('click',function(){
			var target=tab.getAttribute('data-tab');
			tabs.forEach(function(item){item.classList.toggle('is-active',item===tab);});
			panels.forEach(function(panel){panel.classList.toggle('is-active',panel.getAttribute('data-panel')===target);});
			filter();
		});
	});
	if(search){search.addEventListener('input',filter);}
	filter();
}());

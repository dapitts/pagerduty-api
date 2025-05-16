<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row">
	<div class="col-md-12">		
		<ol class="breadcrumb">
			<li><a href="/customer-management">Customer Management</a></li>
			<li><a href="/customer-management/customer/<?php echo $client_code; ?>">Customer Overview</a></li>
			<li><a href="/customer-management/pagerduty/<?php echo $client_code; ?>">PagerDuty API</a></li>
			<li class="active">Create</li>
		</ol>	
	</div>
</div>
<div class="row">
	<div class="col-md-12">		
		<div class="panel panel-light">
			<div class="panel-heading">
				<div class="clearfix">
					<div class="pull-left">
						<h3>PagerDuty API Integration</h3>
						<h4>Create</h4>
					</div>
					<div class="pull-right">
						<a href="/customer-management/pagerduty/<?php echo $client_code; ?>" type="button" class="btn btn-default">Cancel &amp; Return</a>
					</div>
				</div>
			</div>			
			<?php echo form_open($this->uri->uri_string()); ?>
				<div class="panel-body">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group <?php echo form_error('routing_key') ? 'has-error':''; ?>">
								<label class="control-label" for="routing_key">routing key <span class="help-block inline small">( This is the 32 character "Integration Key" for an integration on a service or on a global ruleset. )</span></label>
								<input type="text" class="form-control" id="routing_key" name="routing_key" placeholder="Enter Routing Key" value="<?php echo set_value('routing_key'); ?>">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 text-right">
							<button type="submit" class="btn btn-success" data-loading-text="Updating...">Update</button>
						</div>
					</div>
	  			</div>
			<?php echo form_close(); ?>			
		</div>		
	</div>
</div>